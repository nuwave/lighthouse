<?php

namespace Nuwave\Lighthouse;

use GraphQL\Error\Error;
use GraphQL\Type\Schema;
use GraphQL\GraphQL as GraphQLBase;
use GraphQL\Executor\ExecutionResult;
use GraphQL\Validator\Rules\QueryDepth;
use Nuwave\Lighthouse\Support\Pipeline;
use GraphQL\Validator\DocumentValidator;
use Nuwave\Lighthouse\Events\BuildingAST;
use Nuwave\Lighthouse\Schema\NodeRegistry;
use Nuwave\Lighthouse\Schema\TypeRegistry;
use Nuwave\Lighthouse\Schema\SchemaBuilder;
use GraphQL\Validator\Rules\QueryComplexity;
use Nuwave\Lighthouse\Schema\AST\ASTBuilder;
use Nuwave\Lighthouse\Schema\AST\DocumentAST;
use Nuwave\Lighthouse\Schema\DirectiveRegistry;
use GraphQL\Validator\Rules\DisableIntrospection;
use Nuwave\Lighthouse\Execution\DataLoader\BatchLoader;
use Nuwave\Lighthouse\Schema\Source\SchemaSourceProvider;
use Nuwave\Lighthouse\Schema\Extensions\ExtensionRegistry;

class GraphQL
{
    /** @var Schema */
    protected $executableSchema;

    /** @var DocumentAST */
    protected $documentAST;

    /** @var ExtensionRegistry */
    protected $extensionRegistry;

    /** @var SchemaBuilder */
    protected $schemaBuilder;

    /** @var SchemaSourceProvider */
    protected $schemaSourceProvider;

    /** @var Pipeline */
    protected $pipeline;

    /**
     * @param ExtensionRegistry    $extensionRegistry
     * @param SchemaBuilder        $schemaBuilder
     * @param SchemaSourceProvider $schemaSourceProvider
     * @param Pipeline             $pipeline
     */
    public function __construct(ExtensionRegistry $extensionRegistry, SchemaBuilder $schemaBuilder, SchemaSourceProvider $schemaSourceProvider, Pipeline $pipeline)
    {
        $this->extensionRegistry = $extensionRegistry;
        $this->schemaBuilder = $schemaBuilder;
        $this->schemaSourceProvider = $schemaSourceProvider;
        $this->pipeline = $pipeline;
    }

    /**
     * Execute a set of batched queries on the lighthouse schema and return a
     * collection of ExecutionResults.
     *
     * @param array      $requests
     * @param mixed|null $context
     * @param mixed|null $rootValue
     *
     * @return ExecutionResult[]
     */
    public function executeBatchedQueries(array $requests, $context = null, $rootValue = null): array
    {
        return collect($requests)->map(function ($request, $index) use ($context, $rootValue) {
            $this->extensionRegistry->batchedQueryDidStart($index);

            $result = $this->executeQuery(
                array_get($request, 'query', ''),
                $context,
                array_get($request, 'variables', []),
                $rootValue
            );

            $this->extensionRegistry->batchedQueryDidEnd($result, $index);

            return $result;
        })->all();
    }

    /**
     * Execute a GraphQL query on the Lighthouse schema and return the raw ExecutionResult.
     *
     * To render the ExecutionResult, you will probably want to call `->toArray($debug)` on it,
     * with $debug being a combination of flags in \GraphQL\Error\Debug
     *
     * @param string $query
     * @param null   $context
     * @param array  $variables
     * @param null   $rootValue
     *
     * @throws Exceptions\DirectiveException
     * @throws Exceptions\ParseException
     *
     * @return ExecutionResult
     */
    public function executeQuery(string $query, $context = null, $variables = [], $rootValue = null): ExecutionResult
    {
        $result = GraphQLBase::executeQuery(
            $this->prepSchema(),
            $query,
            $rootValue,
            $context,
            $variables,
            app('request')->input('operationName'),
            null,
            $this->getValidationRules() + DocumentValidator::defaultRules()
        );

        $result->extensions = $this->extensionRegistry->jsonSerialize();

        $result->setErrorsHandler(
            function (array $errors, callable $formatter): array {
                // Do report: Errors that are not client safe, schema definition errors
                // Do not report: Validation, Errors that are meant for the final user
                // Misformed Queries: Log if you are dog-fooding your app

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
     * @throws Exceptions\DirectiveException
     * @throws Exceptions\ParseException
     *
     * @return Schema
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
     * Construct the validation rules from the config.
     *
     * @return array
     */
    protected function getValidationRules(): array
    {
        return [
            new QueryComplexity(config('lighthouse.security.max_query_complexity', 0)),
            new QueryDepth(config('lighthouse.security.max_query_depth', 0)),
            new DisableIntrospection(config('lighthouse.security.disable_introspection', false)),
        ];
    }

    /**
     * Get instance of DocumentAST.
     *
     * @throws Exceptions\ParseException
     *
     * @return DocumentAST
     */
    public function documentAST(): DocumentAST
    {
        if (empty($this->documentAST)) {
            $this->documentAST = config('lighthouse.cache.enable')
                ? app('cache')->rememberForever(config('lighthouse.cache.key'), function () {
                    return $this->buildAST();
                })
                : $this->buildAST();
        }

        return $this->documentAST;
    }

    /**
     * Get the schema string and build an AST out of it.
     *
     * @throws Exceptions\ParseException
     *
     * @return DocumentAST
     */
    protected function buildAST(): DocumentAST
    {
        $schemaString = $this->schemaSourceProvider->getSchemaString();

        // Allow to register listeners that add in additional schema definitions.
        // This can be used by plugins to hook into the schema building process
        // while still allowing the user to add in their schema as usual.
        $additionalSchemas = collect(
            event(
                new BuildingAST($schemaString)
            )
        )->implode("\n");

        return ASTBuilder::generate($schemaString."\n".$additionalSchemas);
    }

    /**
     * ATTENTION
     * ONLY DEPRECATED METHODS FROM THIS POINT ON.
     *
     * Do not use the functions below, they will be removed in v3
     *
     * ONLY DEPRECATED METHODS FROM THIS POINT ON
     * ATTENTION
     */

    /**
     * Return an instance of a BatchLoader for a specific field.
     *
     * @param string $loaderClass
     * @param array  $pathToField
     * @param array  $constructorArgs Those arguments are passed to the constructor of the instance
     *
     * @throws \Exception
     *
     * @return BatchLoader
     *
     * @deprecated in favour of BatchLoader::instance()
     */
    public function batchLoader(string $loaderClass, array $pathToField, array $constructorArgs = []): BatchLoader
    {
        return BatchLoader::instance($loaderClass, $pathToField, $constructorArgs);
    }

    /**
     * @throws Exceptions\DirectiveException
     *
     * @return Schema
     *
     * @deprecated in v3 in favour of prepSchema
     */
    public function buildSchema(): Schema
    {
        return $this->prepSchema();
    }

    /**
     * @param string $query
     * @param mixed  $context
     * @param array  $variables
     * @param mixed  $rootValue
     *
     * @throws Exceptions\DirectiveException
     *
     * @return array
     *
     * @deprecated use executeQuery()->toArray() instead. This allows to control the debug settings.
     */
    public function execute(string $query, $context = null, $variables = [], $rootValue = null): array
    {
        return $this->queryAndReturnResult($query, $context, $variables, $rootValue)->toArray();
    }

    /**
     * @param string $query
     * @param mixed  $context
     * @param array  $variables
     * @param mixed  $rootValue
     *
     * @throws Exceptions\DirectiveException
     *
     * @return \GraphQL\Executor\ExecutionResult
     *
     * @deprecated renamed to executeQuery to match webonyx/graphql-php
     */
    public function queryAndReturnResult(string $query, $context = null, $variables = [], $rootValue = null): ExecutionResult
    {
        return $this->executeQuery($query, $context, $variables, $rootValue);
    }

    /**
     * @return DirectiveRegistry
     *
     * @deprecated Use resolve() instead, will be removed in v3
     */
    public function directives(): DirectiveRegistry
    {
        return resolve(DirectiveRegistry::class);
    }

    /**
     * @return TypeRegistry
     *
     * @deprecated Use resolve() instead, will be removed in v3
     */
    public function types(): TypeRegistry
    {
        return resolve(TypeRegistry::class);
    }

    /**
     * @return TypeRegistry
     *
     * @deprecated Use resolve() instead, will be removed in v3
     */
    public function schema(): TypeRegistry
    {
        return $this->types();
    }

    /**
     * @return NodeRegistry
     *
     * @deprecated Use resolve() instead, will be removed in v3
     */
    public function nodes(): NodeRegistry
    {
        return resolve(NodeRegistry::class);
    }

    /**
     * @return ExtensionRegistry
     *
     * @deprecated Use resolve() instead, will be removed in v3
     */
    public function extensions(): ExtensionRegistry
    {
        return resolve(ExtensionRegistry::class);
    }
}
