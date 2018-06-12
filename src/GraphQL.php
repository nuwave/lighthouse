<?php

namespace Nuwave\Lighthouse;

use GraphQL\GraphQL as GraphQLBase;
use GraphQL\Type\Schema;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\Cache;
use Nuwave\Lighthouse\Schema\AST\ASTBuilder;
use Nuwave\Lighthouse\Schema\AST\DocumentAST;
use Nuwave\Lighthouse\Schema\AST\SchemaStitcher;
use Nuwave\Lighthouse\Schema\DirectiveRegistry;
use Nuwave\Lighthouse\Schema\MiddlewareRegistry;
use Nuwave\Lighthouse\Schema\NodeRegistry;
use Nuwave\Lighthouse\Schema\SchemaBuilder;
use Nuwave\Lighthouse\Schema\TypeRegistry;
use Nuwave\Lighthouse\Support\Traits\CanFormatError;

class GraphQL
{
    use CanFormatError;

    /** @var DirectiveRegistry */
    protected $directiveRegistry;

    /**
     * @return DirectiveRegistry
     */
    public function directives()
    {
        return $this->directiveRegistry;
    }

    /** @var MiddlewareRegistry */
    protected $middlewareRegistry;

    /**
     * @return MiddlewareRegistry
     */
    public function middleware()
    {
        return $this->middlewareRegistry;
    }

    /** @var NodeRegistry */
    protected $nodeRegistry;

    /**
     * @return NodeRegistry
     */
    public function nodes()
    {
        return $this->nodeRegistry;
    }

    /** @var TypeRegistry */
    protected $typeRegistry;

    /**
     * @return TypeRegistry
     */
    public function types()
    {
        return $this->typeRegistry;
    }

    /**
     * GraphQL constructor.
     *
     * @param DirectiveRegistry  $directiveRegistry
     * @param MiddlewareRegistry $middlewareRegistry
     * @param NodeRegistry       $nodeRegistry
     * @param TypeRegistry       $typeRegistry
     */
    public function __construct(DirectiveRegistry $directiveRegistry, MiddlewareRegistry $middlewareRegistry, NodeRegistry $nodeRegistry, TypeRegistry $typeRegistry)
    {
        $this->directiveRegistry = $directiveRegistry;
        $this->middlewareRegistry = $middlewareRegistry;
        $this->nodeRegistry = $nodeRegistry;
        $this->typeRegistry = $typeRegistry;
    }

    /** @var Schema */
    protected $graphqlSchema;

    /**
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
            $this->prepSchema(),
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
        return App::environment('production') && config('cache.enable');
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
}
