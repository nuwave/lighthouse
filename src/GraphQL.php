<?php

namespace Nuwave\Lighthouse;

use GraphQL\GraphQL as GraphQLBase;
use GraphQL\Type\Schema;
use Nuwave\Lighthouse\Schema\CacheManager;
use Nuwave\Lighthouse\Schema\Factories\DirectiveFactory;
use Nuwave\Lighthouse\Schema\MiddlewareManager;
use Nuwave\Lighthouse\Schema\NodeContainer;
use Nuwave\Lighthouse\Schema\SchemaBuilder;
use Nuwave\Lighthouse\Schema\Utils\SchemaStitcher;
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
     * Directive container.
     *
     * @var DirectiveFactory
     */
    protected $directives;

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
     * Prepare graphql schema.
     */
    public function prepSchema()
    {
        $this->graphqlSchema = $this->buildSchema();
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

        return GraphQLBase::executeAndReturnResult(
            $schema,
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
        $schema = $this->cache()->get(function () {
            return $this->stitcher()->stitch(
                config('lighthouse.global_id_field', '_id'),
                config('lighthouse.schema.register')
            );
        });

        return $this->schema()->build($schema);
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
     * Get an instance of the directive container.
     *
     * @return DirectiveFactory
     */
    public function directives()
    {
        if (! $this->directives) {
            $this->directives = app(DirectiveFactory::class);
        }

        return $this->directives;
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
     * Get instance of cache manager.
     *
     * @return CacheManager
     */
    public function cache()
    {
        if (! $this->cache) {
            $this->cache = app(CacheManager::class);
        }

        return $this->cache;
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
