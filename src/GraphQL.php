<?php

namespace Nuwave\Lighthouse;

use GraphQL\Error\Error;
use GraphQL\Type\Schema;
use GraphQL\GraphQL as GraphQLBase;
use Illuminate\Support\Facades\Cache;
use GraphQL\Executor\ExecutionResult;
use Illuminate\Support\Facades\Request;
use Nuwave\Lighthouse\Support\Pipeline;
use GraphQL\Validator\Rules\QueryDepth;
use Nuwave\Lighthouse\Events\BuildingAST;
use Nuwave\Lighthouse\Schema\TypeRegistry;
use Nuwave\Lighthouse\Schema\NodeRegistry;
use Nuwave\Lighthouse\Schema\SchemaBuilder;
use Nuwave\Lighthouse\Schema\AST\ASTBuilder;
use GraphQL\Validator\Rules\QueryComplexity;
use Nuwave\Lighthouse\Schema\AST\DocumentAST;
use Nuwave\Lighthouse\Execution\HandlesErrors;
use Nuwave\Lighthouse\Schema\DirectiveRegistry;
use Nuwave\Lighthouse\Schema\MiddlewareRegistry;
use GraphQL\Validator\Rules\DisableIntrospection;
use Nuwave\Lighthouse\Support\DataLoader\BatchLoader;
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
     * @param ExtensionRegistry $extensionRegistry
     * @param SchemaBuilder $schemaBuilder
     * @param SchemaSourceProvider $schemaSourceProvider
     * @param Pipeline $pipeline
     */
    public function __construct(ExtensionRegistry $extensionRegistry, SchemaBuilder $schemaBuilder, SchemaSourceProvider $schemaSourceProvider, Pipeline $pipeline)
    {
        $this->extensionRegistry = $extensionRegistry;
        $this->schemaBuilder = $schemaBuilder;
        $this->schemaSourceProvider = $schemaSourceProvider;
        $this->pipeline = $pipeline;
    }

    /**
     * Execute a GraphQL query on the Lighthouse schema and return the raw ExecutionResult.
     *
     * To render the ExecutionResult, you will probably want to call `->toArray($debug)` on it,
     * with $debug being a combination of flags in \GraphQL\Error\Debug
     *
     * @param string $query
     * @param null $context
     * @param array $variables
     * @param null $rootValue
     *
     * @return ExecutionResult
     */
    public function executeQuery(string $query, $context = null, $variables = [], $rootValue = null): ExecutionResult
    {
        $schema = $this->executableSchema ?: $this->buildSchema();

        $result = GraphQLBase::executeQuery(
            $schema,
            $query,
            $rootValue,
            $context,
            $variables,
            Request::input('operationName'),
            null,
            $this->getValidationRules()
        );

        $result->extensions = $this->extensionRegistry->jsonSerialize();

        $result->setErrorsHandler(function (array $errors, callable $formatter): array {
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
                        ->then(function (Error $error) use ($formatter){
                            return $formatter($error);
                        });
                },
                $errors
            );
        });

        return $result;
    }

    /**
     * Ensure an executable GraphQL schema is present.
     *
     * @return Schema
     */
    public function prepSchema(): Schema
    {
        return $this->executableSchema = $this->executableSchema ?: $this->buildSchema();
    }

    /**
     * @param string $query
     * @param mixed $context
     * @param array $variables
     * @param mixed $rootValue
     *
     * @return array
     * @deprecated use executeQuery()->toArray() instead. This allows to control the debug settings.
     */
    public function execute(string $query, $context = null, $variables = [], $rootValue = null): array
    {
        return $this->queryAndReturnResult($query, $context, $variables, $rootValue)->toArray();
    }

    /**
     * @param string $query
     * @param mixed $context
     * @param array $variables
     * @param mixed $rootValue
     *
     * @return \GraphQL\Executor\ExecutionResult
     * @deprecated renamed to executeQuery to match webonyx/graphql-php
     */
    public function queryAndReturnResult(string $query, $context = null, $variables = [], $rootValue = null): ExecutionResult
    {
        return $this->executeQuery($query, $context, $variables, $rootValue);
    }

    /**
     * Build a new executable schema.
     *
     * @return Schema
     */
    public function buildSchema(): Schema
    {
        $documentAST = $this->documentAST();

        return $this->schemaBuilder->build($documentAST);
    }

    /**
     * Get instance of DocumentAST.
     *
     * @return DocumentAST
     */
    public function documentAST(): DocumentAST
    {
        if (!$this->documentAST) {
            $this->documentAST = config('lighthouse.cache.enable')
                ? Cache::rememberForever(config('lighthouse.cache.key'), function () {
                    return $this->buildAST();
                })
                : $this->buildAST();
        }

        return $this->documentAST->lock();
    }

    /**
     * Get the schema string and build an AST out of it.
     *
     * @throws \Exception
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

        return ASTBuilder::generate($schemaString . "\n" . $additionalSchemas);
    }

    /**
     * Return an instance of a BatchLoader for a specific field.
     *
     * @param string $loaderClass
     * @param array $pathToField
     * @param array $constructorArgs Those arguments are passed to the constructor of the instance
     *
     * @throws \Exception
     *
     * @return BatchLoader
     */
    public function batchLoader(string $loaderClass, array $pathToField, array $constructorArgs = []): BatchLoader
    {
        // The path to the field serves as the unique key for the instance
        $instanceName = BatchLoader::instanceKey($pathToField);

        // Only register a new instance if it is not already bound
        $instance = app()->bound($instanceName)
            ? resolve($instanceName)
            : app()->instance($instanceName, app()->makeWith($loaderClass, $constructorArgs));

        if (!$instance instanceof BatchLoader) {
            throw new \Exception("The given class '$loaderClass' must resolve to an instance of Nuwave\Lighthouse\Support\DataLoader\BatchLoader");
        }

        return $instance;
    }

    /**
     * @return DirectiveRegistry
     * @deprecated Use resolve() instead, will be removed in v3
     */
    public function directives(): DirectiveRegistry
    {
        return resolve(DirectiveRegistry::class);
    }

    /**
     * @return TypeRegistry
     * @deprecated Use resolve() instead, will be removed in v3
     */
    public function types(): TypeRegistry
    {
        return resolve(TypeRegistry::class);
    }

    /**
     * @return TypeRegistry
     * @deprecated Use resolve() instead, will be removed in v3
     */
    public function schema(): TypeRegistry
    {
        return $this->types();
    }

    /**
     * @return MiddlewareRegistry
     * @deprecated Use resolve() instead, will be removed in v3
     */
    public function middleware(): MiddlewareRegistry
    {
        return resolve(MiddlewareRegistry::class);
    }

    /**
     * @return NodeRegistry
     * @deprecated Use resolve() instead, will be removed in v3
     */
    public function nodes(): NodeRegistry
    {
        return resolve(NodeRegistry::class);
    }

    /**
     * @return ExtensionRegistry
     * @deprecated Use resolve() instead, will be removed in v3
     */
    public function extensions(): ExtensionRegistry
    {
        return resolve(ExtensionRegistry::class);
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
}
