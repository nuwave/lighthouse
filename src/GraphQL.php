<?php

namespace Nuwave\Lighthouse;

use GraphQL\Error\Error;
use GraphQL\Type\Schema;
use Illuminate\Support\Arr;
use GraphQL\GraphQL as GraphQLBase;
use GraphQL\Executor\ExecutionResult;
use GraphQL\Validator\Rules\QueryDepth;
use Nuwave\Lighthouse\Support\Pipeline;
use GraphQL\Validator\DocumentValidator;
use Nuwave\Lighthouse\Events\BuildingAST;
use Nuwave\Lighthouse\Schema\SchemaBuilder;
use GraphQL\Validator\Rules\QueryComplexity;
use Nuwave\Lighthouse\Schema\AST\ASTBuilder;
use Nuwave\Lighthouse\Schema\AST\DocumentAST;
use GraphQL\Validator\Rules\DisableIntrospection;
use Nuwave\Lighthouse\Support\Contracts\GraphQLContext;
use Nuwave\Lighthouse\Schema\Source\SchemaSourceProvider;
use Nuwave\Lighthouse\Schema\Extensions\ExtensionRegistry;

class GraphQL
{
    /**
     * The executable schema.
     *
     * @var \GraphQL\Type\Schema
     */
    protected $executableSchema;

    /**
     * The parsed schema AST.
     *
     * @var \Nuwave\Lighthouse\Schema\AST\DocumentAST
     */
    protected $documentAST;

    /**
     * Th extension registry.
     *
     * @var \Nuwave\Lighthouse\Schema\Extensions\ExtensionRegistry
     */
    protected $extensionRegistry;

    /**
     * The schema builder.
     *
     * @var \Nuwave\Lighthouse\Schema\SchemaBuilder
     */
    protected $schemaBuilder;

    /**
     * The schema source provider.
     *
     * @var \Nuwave\Lighthouse\Schema\Source\SchemaSourceProvider
     */
    protected $schemaSourceProvider;

    /**
     * The pipeline.
     *
     * @var \Nuwave\Lighthouse\Support\Pipeline
     */
    protected $pipeline;

    /**
     * The current batch index.
     *
     * @var int|null
     */
    protected $currentBatchIndex = null;

    /**
     * GraphQL constructor.
     *
     * @param  \Nuwave\Lighthouse\Schema\Extensions\ExtensionRegistry $extensionRegistry
     * @param  \Nuwave\Lighthouse\Schema\SchemaBuilder $schemaBuilder
     * @param  \Nuwave\Lighthouse\Schema\Source\SchemaSourceProvider $schemaSourceProvider
     * @param  \Nuwave\Lighthouse\Support\Pipeline $pipeline
     * @retutn void
     */
    public function __construct(
        ExtensionRegistry $extensionRegistry,
        SchemaBuilder $schemaBuilder,
        SchemaSourceProvider $schemaSourceProvider,
        Pipeline $pipeline
    ) {
        $this->extensionRegistry = $extensionRegistry;
        $this->schemaBuilder = $schemaBuilder;
        $this->schemaSourceProvider = $schemaSourceProvider;
        $this->pipeline = $pipeline;
    }

    /**
     * Returns the index of the current batch if we are resolving
     * a batched query or `null` if we are resolving a single query.
     *
     * @return int|null
     */
    public function currentBatchIndex(): ?int
    {
        return $this->currentBatchIndex;
    }

    /**
     * Execute a set of batched queries on the lighthouse schema and return a
     * collection of ExecutionResults.
     *
     * @param  array  $requests
     * @param  \Nuwave\Lighthouse\Support\Contracts\GraphQLContext  $context
     * @param  mixed|null  $rootValue
     * @return \GraphQL\Executor\ExecutionResult[]
     */
    public function executeBatchedQueries(array $requests, GraphQLContext $context, $rootValue = null): array
    {
        return collect($requests)
            ->map(function (array $request, int $index) use ($context, $rootValue) {
                $this->currentBatchIndex = $index;
                $this->extensionRegistry->batchedQueryDidStart($index);

                $result = $this->executeQuery(
                    Arr::get($request, 'query', ''),
                    $context,
                    Arr::get($request, 'variables', []),
                    $rootValue
                );

                $this->extensionRegistry->batchedQueryDidEnd($result, $index);

                return $result;
            })
            ->all();
    }

    /**
     * Execute a GraphQL query on the Lighthouse schema and return the raw ExecutionResult.
     *
     * To render the ExecutionResult, you will probably want to call `->toArray($debug)` on it,
     * with $debug being a combination of flags in \GraphQL\Error\Debug
     *
     * @param  string $query
     * @param  \Nuwave\Lighthouse\Support\Contracts\GraphQLContext  $context
     * @param  mixed[] $variables
     * @param  mixed|null $rootValue
     * @param  string|null $operationName
     * @return \GraphQL\Executor\ExecutionResult
     */
    public function executeQuery(
        string $query,
        GraphQLContext $context,
        ?array $variables = [],
        $rootValue = null,
        ?string $operationName = null
    ): ExecutionResult {
        $operationName = $operationName ?: app('request')->input('operationName');

        $result = GraphQLBase::executeQuery(
            $this->prepSchema(),
            $query,
            $rootValue,
            $context,
            $variables,
            $operationName,
            null,
            $this->getValidationRules() + DocumentValidator::defaultRules()
        );

        $result->extensions = $this->extensionRegistry->jsonSerialize();

        $result->setErrorsHandler(
            function (array $errors, callable $formatter): array {
                // Do report: Errors that are not client safe, schema definition errors
                // Do not report: Validation, Errors that are meant for the final user
                // Malformed Queries: Log if you are dogfooding your app

                /**
                 * Handlers are defined as classes in the config.
                 * They must implement the Interface \Nuwave\Lighthouse\Execution\ErrorHandler
                 * This allows the user to register multiple handlers and pipe the errors through.
                 */
                $handlers = config('lighthouse.error_handlers', []);

                return array_map(
                    function (Error $error) use ($handlers, $formatter) {
                        return $this->pipeline
                            ->send($error)
                            ->through($handlers)
                            ->then(function (Error $error) use ($formatter) {
                                return $formatter($error);
                            });
                    },
                    $errors
                );
            }
        );

        return $result;
    }

    /**
     * Ensure an executable GraphQL schema is present.
     *
     * @return \GraphQL\Type\Schema
     */
    public function prepSchema(): Schema
    {
        if (empty($this->executableSchema)) {
            $this->executableSchema = $this->schemaBuilder->build(
                $this->documentAST()
            );
        }

        return $this->executableSchema;
    }

    /**
     * Construct the validation rules with values given in the config.
     *
     * @return \GraphQL\Validator\Rules\ValidationRule[]
     */
    protected function getValidationRules(): array
    {
        return [
            QueryComplexity::class => new QueryComplexity(config('lighthouse.security.max_query_complexity', 0)),
            QueryDepth::class => new QueryDepth(config('lighthouse.security.max_query_depth', 0)),
            DisableIntrospection::class => new DisableIntrospection(config('lighthouse.security.disable_introspection', false)),
        ];
    }

    /**
     * Get instance of DocumentAST.
     *
     * @return \Nuwave\Lighthouse\Schema\AST\DocumentAST
     */
    public function documentAST(): DocumentAST
    {
        if (empty($this->documentAST)) {
            $this->documentAST = config('lighthouse.cache.enable')
                ? app('cache')
                    ->rememberForever(
                        config('lighthouse.cache.key'),
                        function (): DocumentAST {
                            return $this->buildAST();
                        }
                    )
                : $this->buildAST();
        }

        return $this->documentAST;
    }

    /**
     * Get the schema string and build an AST out of it.
     *
     * @return \Nuwave\Lighthouse\Schema\AST\DocumentAST
     */
    protected function buildAST(): DocumentAST
    {
        $schemaString = $this->schemaSourceProvider->getSchemaString();

        // Allow to register listeners that add in additional schema definitions.
        // This can be used by plugins to hook into the schema building process
        // while still allowing the user to add in their schema as usual.
        $additionalSchemas = collect(
            event(new BuildingAST($schemaString))
        )->implode(PHP_EOL);

        return ASTBuilder::generate($schemaString.PHP_EOL.$additionalSchemas);
    }
}
