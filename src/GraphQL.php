<?php

namespace Nuwave\Lighthouse;

use GraphQL\GraphQL as GraphQLBase;
use GraphQL\Type\Schema;
use Illuminate\Support\Facades\Cache;
use Nuwave\Lighthouse\Schema\AST\ASTBuilder;
use Nuwave\Lighthouse\Schema\AST\DocumentAST;
use Nuwave\Lighthouse\Schema\AST\SchemaStitcher;
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
     * Create instance of graphql container.
     *
     * @param DirectiveRegistry $directives
     * @param TypeRegistry      $types
     * @param MiddlewareManager $middleware
     * @param NodeContainer     $nodes
     */
    public function __construct(
        DirectiveRegistry $directives,
        TypeRegistry $types,
        MiddlewareManager $middleware,
        NodeContainer $nodes
    ) {
        $this->directives = $directives;
        $this->types = $types;
        $this->middleware = $middleware;
        $this->nodes = $nodes;
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
                        'code'    => $error->getCode(),
                        'message' => $error->getMessage(),
                        'trace'   => $error->getTraceAsString(),
                    ]);
                }
            }

            return [
                'data'   => $result->data,
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
        $this->documentAST->lock();

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
        $documentAST = $this->documentAST();

        return (new SchemaBuilder())->build($documentAST);
    }

    /**
     * Get instance of DocumentAST.
     *
     * @return DocumentAST
     */
    public function documentAST()
    {
        if (! $this->documentAST) {
            $this->documentAST = config('lighthouse.cache.enable')
                ? Cache::rememberForever(config('lighthouse.cache.key'), function () {
                    return $this->buildAST();
                })
                : $this->buildAST();
        }

        return $this->documentAST;
    }

    /**
     * Temporary workaround to allow injecting a different schema when testing.
     *
     * @param DocumentAST $documentAST
     */
    public function setDocumentAST(DocumentAST $documentAST)
    {
        $this->documentAST = $documentAST;
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
     * Get the directive registry instance.
     *
     * @return DirectiveRegistry
     */
    public function directives()
    {
        return $this->directives;
    }

    /**
     * Get the type registry instance.
     *
     * @return TypeRegistry
     */
    public function types()
    {
        return $this->types;
    }

    /**
     * * Get the type registry instance.
     *
     * @return TypeRegistry
     *
     * @deprecated in favour of types()
     */
    public function schema()
    {
        return $this->types();
    }

    /**
     * Get the middleware manager instance.
     *
     * @return MiddlewareManager
     */
    public function middleware()
    {
        return $this->middleware;
    }

    /**
     * Get the instance of the node container.
     *
     * @return NodeContainer
     */
    public function nodes()
    {
        return $this->nodes;
    }
}
