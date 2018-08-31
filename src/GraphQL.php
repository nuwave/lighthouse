<?php

namespace Nuwave\Lighthouse;

use GraphQL\Type\Schema;
use GraphQL\GraphQL as GraphQLBase;
use Illuminate\Support\Facades\Cache;
use GraphQL\Executor\ExecutionResult;
use Illuminate\Support\Facades\Request;
use GraphQL\Validator\Rules\QueryDepth;
use Nuwave\Lighthouse\Schema\TypeRegistry;
use Nuwave\Lighthouse\Schema\SchemaBuilder;
use Nuwave\Lighthouse\Schema\NodeContainer;
use Nuwave\Lighthouse\Schema\AST\ASTBuilder;
use GraphQL\Validator\Rules\QueryComplexity;
use Nuwave\Lighthouse\Schema\AST\DocumentAST;
use Nuwave\Lighthouse\Schema\MiddlewareManager;
use Nuwave\Lighthouse\Schema\DirectiveRegistry;
use GraphQL\Validator\Rules\DisableIntrospection;
use Nuwave\Lighthouse\Execution\ExceptionFormatter;
use Nuwave\Lighthouse\Support\Exceptions\NewHandler;
use Nuwave\Lighthouse\Support\DataLoader\BatchLoader;
use Nuwave\Lighthouse\Support\Contracts\ErrorsHandler;
use Nuwave\Lighthouse\Schema\Source\SchemaSourceProvider;
use Nuwave\Lighthouse\Schema\Extensions\ExtensionRegistry;

class GraphQL
{
    /** @var Schema */
    protected $executableSchema;

    /** @var DocumentAST */
    protected $documentAST;

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
     * @deprecated use executeQuery()->toArray() instead
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
     * @deprecated renamed to executeQuery
     */
    public function queryAndReturnResult(string $query, $context = null, $variables = [], $rootValue = null): ExecutionResult
    {
        return $this->executeQuery($query, $context, $variables, $rootValue);
    }

    /**
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

        $result->extensions = resolve(ExtensionRegistry::class)->toArray();
        $result->setErrorFormatter([ExceptionFormatter::class, 'format']);

        return $result;
    }

    /**
     * Build a new executable schema.
     *
     * @return Schema
     */
    public function buildSchema(): Schema
    {
        $documentAST = $this->documentAST();

        return (new SchemaBuilder())->build($documentAST);
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
     * @return DocumentAST
     */
    protected function buildAST(): DocumentAST
    {
        $schemaString = app(SchemaSourceProvider::class)->getSchemaString();

        return ASTBuilder::generate($schemaString);
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
     * @return MiddlewareManager
     * @deprecated Use resolve() instead, will be removed in v3
     */
    public function middleware(): MiddlewareManager
    {
        return resolve(MiddlewareManager::class);
    }

    /**
     * @return NodeContainer
     * @deprecated Use resolve() instead, will be removed in v3
     */
    public function nodes(): NodeContainer
    {
        return resolve(NodeContainer::class);
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
     * @return ErrorsHandler
     * @deprecated Use resolve() instead, will be removed in v3
     */
    public function exceptionHandler(): ErrorsHandler
    {
        return resolve(ErrorsHandler::class);
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
