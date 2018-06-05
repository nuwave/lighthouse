<?php

namespace Nuwave\Lighthouse\Schema;

use GraphQL\Language\AST\OperationDefinitionNode;
use GraphQL\Utils\AST;
use Nuwave\Lighthouse\Support\Traits\CanParseTypes;

class MiddlewareManager
{
    use CanParseTypes;

    /**
     * Registered query middleware.
     *
     * @var array
     */
    protected $queries = [];

    /**
     * Registered mutation middleware.
     *
     * @var array
     */
    protected $mutations = [];

    /**
     * Handle request.
     *
     * @param string $request
     *
     * @return array
     */
    public function forRequest($request)
    {
        return collect($this->parseSchema($request)->definitions)
            ->filter(function ($def) {
                return $def instanceof OperationDefinitionNode;
            })->map(function (OperationDefinitionNode $node) {
                $definition = AST::toArray($node);
                $operation = array_get($definition, 'operation');
                $fields = array_pluck(array_get($definition, 'selectionSet.selections', []), 'name.value');

                return $this->operation($operation, $fields);
            })
            ->collapse()
            ->unique()
            ->toArray();
    }

    /**
     * Register query middleware.
     *
     * @param string $name
     * @param array  $middleware
     *
     * @return array
     */
    public function registerQuery($name, array $middleware)
    {
        $this->queries = array_merge($this->queries, [$name => $middleware]);
    }

    /**
     * Register mutation middleware.
     *
     * @param string $name
     * @param array  $middleware
     *
     * @return array
     */
    public function registerMutation($name, array $middleware)
    {
        $this->mutations = array_merge($this->mutations, [$name => $middleware]);
    }

    /**
     * Get middleware for operation.
     *
     * @param string $operation
     * @param array  $fields
     *
     * @return array
     */
    public function operation($operation, array $fields)
    {
        if ('mutation' === $operation) {
            return array_collapse(array_map(function ($field) {
                return $this->mutation($field);
            }, $fields));
        } elseif ('query' === $operation) {
            return array_collapse(array_map(function ($field) {
                return $this->query($field);
            }, $fields));
        }

        return [];
    }

    /**
     * Get middleware for query.
     *
     * @param string $name
     *
     * @return array
     */
    public function query($name)
    {
        return array_get($this->queries, $name, []);
    }

    /**
     * Get middleware for mutation.
     *
     * @param string $name
     *
     * @return array
     */
    public function mutation($name)
    {
        return array_get($this->mutations, $name, []);
    }

    /**
     * Get middleware by node.
     *
     * @param OperationDefinitionNode $node
     * @param  string
     *
     * @return array
     */
    protected function byNode(OperationDefinitionNode $node, $operation)
    {
    }
}
