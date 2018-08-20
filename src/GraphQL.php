<?php

namespace Nuwave\Lighthouse;

use GraphQL\Deferred;
use GraphQL\Type\Schema;
use GraphQL\GraphQL as GraphQLBase;
use GraphQL\Executor\ExecutionResult;
use Illuminate\Support\Facades\Cache;
use GraphQL\Validator\Rules\QueryDepth;
use Illuminate\Support\Facades\Request;
use Nuwave\Lighthouse\Schema\TypeRegistry;
use Nuwave\Lighthouse\Schema\NodeContainer;
use Nuwave\Lighthouse\Schema\SchemaBuilder;
use GraphQL\Validator\Rules\QueryComplexity;
use Nuwave\Lighthouse\Schema\AST\ASTBuilder;
use Nuwave\Lighthouse\Schema\AST\DocumentAST;
use Nuwave\Lighthouse\Schema\DirectiveRegistry;
use Nuwave\Lighthouse\Schema\MiddlewareManager;
use Nuwave\Lighthouse\Support\Exceptions\Handler;
use GraphQL\Validator\Rules\DisableIntrospection;
use Nuwave\Lighthouse\Support\Contracts\ExceptionHandler;
use Nuwave\Lighthouse\Schema\Extensions\ExtensionRequest;
use Nuwave\Lighthouse\Schema\Source\SchemaSourceProvider;
use Nuwave\Lighthouse\Schema\Extensions\ExtensionRegistry;

class GraphQL
{
    /**
     * Directive registry container.
     *
     * @var DirectiveRegistry
     */
    protected $directives;

    /**
     * Type registry container.
     *
     * @var TypeRegistry
     */
    protected $types;

    /**
     * Instance of node container.
     *
     * @var NodeContainer
     */
    protected $nodes;

    /**
     * Middleware manager.
     *
     * @var MiddlewareManager
     */
    protected $middleware;

    /**
     * Extension registry.
     *
     * @var ExtensionRegistry
     */
    protected $extensions;

    /**
     * GraphQL Schema.
     *
     * @var Schema
     */
    protected $graphqlSchema;

    /**
     * Local instance of DocumentAST.
     *
     * @var DocumentAST
     */
    protected $documentAST;

    /**
     * Exception handler.
     *
     * @var Handler
     */
    protected $exceptionHandler;

    /**
     * Create instance of graphql container.
     *
     * @param DirectiveRegistry $directives
     * @param TypeRegistry $types
     * @param MiddlewareManager $middleware
     * @param NodeContainer $nodes
     * @param ExtensionRegistry $extensions
     * @param ExceptionHandler $exceptionHandler
     */
    public function __construct(
        DirectiveRegistry $directives,
        TypeRegistry $types,
        MiddlewareManager $middleware,
        NodeContainer $nodes,
        ExtensionRegistry $extensions,
        ExceptionHandler $exceptionHandler
    ) {
        $this->directives = $directives;
        $this->types = $types;
        $this->middleware = $middleware;
        $this->nodes = $nodes;
        $this->extensions = $extensions;
        $this->exceptionHandler = $exceptionHandler;
    }

    /**
     * Prepare graphql schema.
     *
     * @return Schema
     */
    public function prepSchema(): Schema
    {
        return $this->graphqlSchema = $this->graphqlSchema ?: $this->buildSchema();
    }

    /**
     * Execute GraphQL query.
     *
     * @param string $query
     * @param mixed  $context
     * @param array  $variables
     * @param mixed  $rootValue
     *
     * @return array
     */
    public function execute(string $query, $context = null, $variables = [], $rootValue = null): array
    {
        $result = $this->queryAndReturnResult($query, $context, $variables, $rootValue);
        $result->setErrorsHandler([$this->exceptionHandler(), 'handler']);

        $data = $result->toArray();

        if(!isset($data['extensions'])) {
            $data['extensions'] = [];
        }

        return $data;
    }

    /**
     * Execute GraphQL query.
     *
     * @param string $query
     * @param mixed  $context
     * @param array  $variables
     * @param mixed  $rootValue
     *
     * @return \GraphQL\Executor\ExecutionResult
     */
    public function queryAndReturnResult($query, $context = null, $variables = [], $rootValue = null): ExecutionResult
    {
        $this->extensions->requestDidStart(new ExtensionRequest([
            'request' => Request::instance(),
            'query_string' => $query,
            'operationName' => Request::input('operationName'),
            'variables' => $variables,
        ]));

        $schema = $this->graphqlSchema ?: $this->buildSchema();
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

        $result->extensions = $this->extensions->toArray();

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
        if (! $this->documentAST) {
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
     * Batch field resolver.
     *
     * @param string $abstract
     * @param mixed  $key
     * @param array  $data
     * @param string $name
     *
     * @return \GraphQL\Deferred
     */
    public function batch($abstract, $key, array $data = [], $name = null): Deferred
    {
        $name = $name ?: $abstract;
        $instance = app()->has($name)
            ? resolve($name)
            : app()->instance($name, resolve($abstract));

        return $instance->load($key, $data);
    }

    /**
     * Get the directive registry instance.
     *
     * @return DirectiveRegistry
     */
    public function directives(): DirectiveRegistry
    {
        return $this->directives;
    }

    /**
     * Get the type registry instance.
     *
     * @return TypeRegistry
     */
    public function types(): TypeRegistry
    {
        return $this->types;
    }

    /**
     * Get the type registry instance.
     *
     * @return TypeRegistry
     *
     * @deprecated in favour of types()
     */
    public function schema(): TypeRegistry
    {
        return $this->types();
    }

    /**
     * Get the middleware manager instance.
     *
     * @return MiddlewareManager
     */
    public function middleware(): MiddlewareManager
    {
        return $this->middleware;
    }

    /**
     * Get the instance of the node container.
     *
     * @return NodeContainer
     */
    public function nodes(): NodeContainer
    {
        return $this->nodes;
    }

    /**
     * Get instance of extension registry.
     *
     * @return ExtensionRegistry
     */
    public function extensions(): ExtensionRegistry
    {
        return $this->extensions;
    }

    /**
     * Get the instance of the exception handler.
     *
     * @return ExceptionHandler
     */
    public function exceptionHandler(): ExceptionHandler
    {
        return $this->exceptionHandler;
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
