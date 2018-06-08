<?php

namespace Nuwave\Lighthouse;

use GraphQL\GraphQL as GraphQLBase;
use GraphQL\Type\Schema;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\Cache;
use Nuwave\Lighthouse\Schema\AST\ASTBuilder;
use Nuwave\Lighthouse\Schema\AST\DocumentAST;
use Nuwave\Lighthouse\Schema\AST\SchemaStitcher;
use Nuwave\Lighthouse\Schema\MiddlewareRegistry;
use Nuwave\Lighthouse\Schema\NodeRegistry;
use Nuwave\Lighthouse\Schema\SchemaBuilder;
use Nuwave\Lighthouse\Support\Traits\CanFormatError;

class GraphQL
{
    use CanFormatError;

    /**
     * Schema builder.
     *
     * @var SchemaBuilder
     */
    protected $schema;

    /**
     * Middleware manager.
     *
     * @var MiddlewareRegistry
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
     * Prepare graphql schema.
     */
    const AST_CACHE_KEY = 'lighthouse-schema';

    public function retrieveSchema(): Schema
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
        return GraphQLBase::executeQuery(
            $this->retrieveSchema(),
            $query,
            $rootValue,
            $context,
            $variables
        );
    }

    /**
     * Build a new schema instance.
     *
     * @return Schema
     */
    public function buildSchema()
    {
        $documentAST = $this->shouldCacheAST() ?
            Cache::rememberForever(self::AST_CACHE_KEY, function () {
                return $this->buildAST();
            })
            : $this->buildAST();

        return $this->schema()->build($documentAST);
    }

    protected function shouldCacheAST()
    {
        return App::environment('production') && config('cache.enable');
    }

    /**
     * Get the stitched schema and build an AST out of it.
     *
     * @return DocumentAST
     */
    protected function buildAST()
    {
        $schemaString = $this->stitcher()->stitch(
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
     * @return SchemaBuilder
     */
    public function schema()
    {
        if (! $this->schema) {
            $this->schema = app(SchemaBuilder::class);
        }

        return $this->schema;
    }

    /**
     * Get instance of middle manager.
     *
     * @return MiddlewareRegistry
     */
    public function middleware()
    {
        if (! $this->middleware) {
            $this->middleware = app(MiddlewareRegistry::class);
        }

        return $this->middleware;
    }

    /**
     * Get instance of schema stitcher.
     *
     * @return SchemaStitcher
     */
    public function stitcher()
    {
        if (! $this->stitcher) {
            $this->stitcher = app(SchemaStitcher::class);
        }

        return $this->stitcher;
    }

    /**
     * Instance of Node container.
     *
     * @return NodeRegistry
     */
    public function nodes()
    {
        if (! app()->has(NodeRegistry::class)) {
            return app()->instance(
                NodeRegistry::class,
                resolve(NodeRegistry::class)
            );
        }

        return resolve(NodeRegistry::class);
    }
}
