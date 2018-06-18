<?php

namespace Nuwave\Lighthouse;

use GraphQL\GraphQL as GraphQLBase;
use GraphQL\Type\Schema;
use Nuwave\Lighthouse\Schema\AST\ASTBuilder;
use Nuwave\Lighthouse\Schema\AST\SchemaStitcher;
use Nuwave\Lighthouse\Schema\CacheManager;
use Nuwave\Lighthouse\Schema\DirectiveRegistry;
use Nuwave\Lighthouse\Schema\MiddlewareManager;
use Nuwave\Lighthouse\Schema\NodeContainer;
use Nuwave\Lighthouse\Schema\SchemaBuilder;
use Nuwave\Lighthouse\Schema\TypeRegistry;
use Nuwave\Lighthouse\Support\Traits\CanFormatError;

class GraphQL
{
    use CanFormatError;

    /**
     * Cache manager.
     *
     * @var CacheManager
     */
    protected $cache;

    /**
     * Schema builder.
     *
     * @var SchemaBuilder
     */
    protected $schema;

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
     * Middleware manager.
     *
     * @var MiddlewareManager
     */
    protected $middleware;

    /**
     * Schema Stitcher.
     *
     * @var SchemaStitcher
     */
    protected $stitcher;

    /**
     * GraphQL Schema.
     *
     * @var Schema
     */
    protected $graphqlSchema;

    /**
     * Create instance of graphql container.
     *
     * @param DirectiveRegistry $directives
     */
    public function __construct(DirectiveRegistry $directives, TypeRegistry $types)
    {
        $this->directives = $directives;
        $this->types = $types;
    }

    /**
     * Prepare graphql schema.
     */
    public function prepSchema()
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
    public function execute($query, $context = null, $variables = [], $rootValue = null)
    {
        $result = $this->queryAndReturnResult($query, $context, $variables, $rootValue);

        if (! empty($result->errors)) {
            foreach ($result->errors as $error) {
                if ($error instanceof \Exception) {
                    info('GraphQL Error:', [
                        'code' => $error->getCode(),
                        'message' => $error->getMessage(),
                        'trace' => $error->getTraceAsString(),
                    ]);
                }
            }

            return [
                'data' => $result->data,
                'errors' => array_map([$this, 'formatError'], $result->errors),
            ];
        }

        return ['data' => $result->data];
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
    public function queryAndReturnResult($query, $context = null, $variables = [], $rootValue = null)
    {
        $schema = $this->graphqlSchema ?: $this->buildSchema();

        return GraphQLBase::executeQuery(
            $schema,
            $query,
            $rootValue,
            $context,
            $variables
        );
    }

    /**
     * Build a new executable schema.
     *
     * @return Schema
     */
    public function buildSchema()
    {
        $documentAST = $this->shouldCacheAST()
        ? Cache::rememberForever(config('lighthouse.cache.key'), function () {
            return $this->buildAST();
        })
        : $this->buildAST();

        return (new SchemaBuilder())->build($documentAST);
    }

    /**
     * Determine if the AST should be cached.
     *
     * @return bool
     */
    protected function shouldCacheAST()
    {
        return app()->environment('production') && config('cache.enable');
    }

    /**
     * Get the stitched schema and build an AST out of it.
     *
     * @return DocumentAST
     */
    protected function buildAST()
    {
        $schemaString = SchemaStitcher::stitch(
            config('lighthouse.schema.register')
        );

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
    public function batch($abstract, $key, array $data = [], $name = null)
    {
        $name = $name ?: $abstract;
        $instance = app()->has($name)
        ? resolve($name)
        : app()->instance($name, resolve($abstract));

        return $instance->load($key, $data);
    }

    /**
     * Get an instance of the schema builder.
     *
     * @return TypeRegistry
     */
    public function schema()
    {
        return $this->types();
    }

    /**
     * Get an instance of the directive container.
     *
     * @return DirectiveRegistry
     */
    public function directives()
    {
        return $this->directives;
    }

    /**
     * Get instsance of type container.
     *
     * @return TypeRegistry
     */
    public function types()
    {
        return $this->types;
    }

    /**
     * Get instance of middle manager.
     *
     * @return MiddlewareManager
     */
    public function middleware()
    {
        if (! $this->middleware) {
            $this->middleware = app(MiddlewareManager::class);
        }

        return $this->middleware;
    }

    /**
     * Instance of Node container.
     *
     * @return NodeContainer
     */
    public function nodes()
    {
        if (! app()->has(NodeContainer::class)) {
            return app()->instance(
                NodeContainer::class,
                resolve(NodeContainer::class)
            );
        }

        return resolve(NodeContainer::class);
    }
}
