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
use Nuwave\Lighthouse\Events\ManipulateAST;
use Nuwave\Lighthouse\Schema\SchemaBuilder;
use GraphQL\Validator\Rules\QueryComplexity;
use Nuwave\Lighthouse\Events\StartExecution;
use Nuwave\Lighthouse\Schema\AST\ASTBuilder;
use Nuwave\Lighthouse\Schema\AST\DocumentAST;
use Nuwave\Lighthouse\Events\ManipulateResult;
use Nuwave\Lighthouse\Events\BuildSchemaString;
use Nuwave\Lighthouse\Execution\GraphQLRequest;
use GraphQL\Validator\Rules\DisableIntrospection;
use Nuwave\Lighthouse\Events\BuildExtensionsResponse;
use Nuwave\Lighthouse\Support\Contracts\CreatesContext;
use Nuwave\Lighthouse\Support\Contracts\GraphQLContext;
use Nuwave\Lighthouse\Schema\Source\SchemaSourceProvider;
use Illuminate\Contracts\Events\Dispatcher as EventDispatcher;

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
     * The event dispatcher.
     *
     * @var \Illuminate\Contracts\Events\Dispatcher
     */
    protected $eventDispatcher;

    /**
     * The AST builder.
     *
     * @var \Nuwave\Lighthouse\Schema\AST\ASTBuilder
     */
    protected $astBuilder;

    /**
     * The context factory.
     *
     * @var \Nuwave\Lighthouse\Support\Contracts\CreatesContext
     */
    protected $createsContext;

    /**
     * GraphQL constructor.
     *
     * @param  \Nuwave\Lighthouse\Schema\SchemaBuilder  $schemaBuilder
     * @param  \Nuwave\Lighthouse\Schema\Source\SchemaSourceProvider  $schemaSourceProvider
     * @param  \Nuwave\Lighthouse\Support\Pipeline  $pipeline
     * @param  \Illuminate\Contracts\Events\Dispatcher  $eventDispatcher
     * @param  \Nuwave\Lighthouse\Schema\AST\ASTBuilder  $astBuilder
     * @param  \Nuwave\Lighthouse\Support\Contracts\CreatesContext  $createsContext
     * @return void
     */
    public function __construct(
        SchemaBuilder $schemaBuilder,
        SchemaSourceProvider $schemaSourceProvider,
        Pipeline $pipeline,
        EventDispatcher $eventDispatcher,
        ASTBuilder $astBuilder,
        CreatesContext $createsContext
    ) {
        $this->schemaBuilder = $schemaBuilder;
        $this->schemaSourceProvider = $schemaSourceProvider;
        $this->pipeline = $pipeline;
        $this->eventDispatcher = $eventDispatcher;
        $this->astBuilder = $astBuilder;
        $this->createsContext = $createsContext;
    }

    /**
     * Execute a set of batched queries on the lighthouse schema and return a
     * collection of ExecutionResults.
     *
     * @param  \Nuwave\Lighthouse\Execution\GraphQLRequest  $request
     * @return mixed[]
     */
    public function executeRequest(GraphQLRequest $request): array
    {
        $result = $this->executeQuery(
            $request->query(),
            $this->createsContext->generate(
                app('request')
            ),
            $request->variables(),
            null,
            $request->operationName()
        );

        return $this->applyDebugSettings($result);
    }

    /**
     * Apply the debug settings from the config and get the result as an array.
     *
     * @param  \GraphQL\Executor\ExecutionResult  $result
     * @return mixed[]
     */
    public function applyDebugSettings(ExecutionResult $result): array
    {
        // If debugging is set to false globally, do not add GraphQL specific
        // debugging info either. If it is true, then we fetch the debug
        // level from the Lighthouse configuration.
        return $result->toArray(
            config('app.debug')
                ? config('lighthouse.debug')
                : false
        );
    }

    /**
     * Execute a GraphQL query on the Lighthouse schema and return the raw ExecutionResult.
     *
     * To render the ExecutionResult, you will probably want to call `->toArray($debug)` on it,
     * with $debug being a combination of flags in \GraphQL\Error\Debug
     *
     * @param  string|\GraphQL\Language\AST\DocumentNode  $query
     * @param  \Nuwave\Lighthouse\Support\Contracts\GraphQLContext  $context
     * @param  mixed[]  $variables
     * @param  mixed|null  $rootValue
     * @param  string|null  $operationName
     * @return \GraphQL\Executor\ExecutionResult
     */
    public function executeQuery(
        $query,
        GraphQLContext $context,
        ?array $variables = [],
        $rootValue = null,
        ?string $operationName = null
    ): ExecutionResult {
        // Building the executable schema might take a while to do,
        // so we do it before we fire the StartExecution event.
        // This allows tracking the time for batched queries independently.
        $this->prepSchema();

        $this->eventDispatcher->dispatch(
            new StartExecution
        );

        $result = GraphQLBase::executeQuery(
            $this->executableSchema,
            $query,
            $rootValue,
            $context,
            $variables,
            $operationName,
            null,
            $this->getValidationRules() + DocumentValidator::defaultRules()
        );

        /** @var \Nuwave\Lighthouse\Execution\ExtensionsResponse[] $extensionsResponses */
        $extensionsResponses = (array) $this->eventDispatcher->dispatch(
            new BuildExtensionsResponse
        );

        foreach ($extensionsResponses as $extensionsResponse) {
            if ($extensionsResponse) {
                $result->extensions[$extensionsResponse->key()] = $extensionsResponse->content();
            }
        }

        $result->setErrorsHandler(
            function (array $errors, callable $formatter): array {
                // User defined error handlers, implementing \Nuwave\Lighthouse\Execution\ErrorHandler
                // This allows the user to register multiple handlers and pipe the errors through.
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

        // Allow listeners to manipulate the result after each resolved query
        $this->eventDispatcher->dispatch(
            new ManipulateResult($result)
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
                    ->remember(
                        config('lighthouse.cache.key'),
                        config('lighthouse.cache.ttl'),
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
        $additionalSchemas = (array) $this->eventDispatcher->dispatch(
            new BuildSchemaString($schemaString)
        );

        $documentAST = $this->astBuilder->build(
            implode(
                PHP_EOL,
                Arr::prepend($additionalSchemas, $schemaString)
            )
        );

        // Listeners may manipulate the DocumentAST that is passed by reference
        // into the ManipulateAST event. This can be useful for extensions
        // that want to programmatically change the schema.
        $this->eventDispatcher->dispatch(
            new ManipulateAST($documentAST)
        );

        return $documentAST;
    }
}
