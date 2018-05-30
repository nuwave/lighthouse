<?php

namespace Nuwave\Lighthouse\Schema;

use GraphQL\Language\AST\OperationDefinitionNode;
use GraphQL\Language\AST\SelectionNode;
use GraphQL\Utils\AST;
use Nuwave\Lighthouse\Schema\Utils\DocumentAST;

class MiddlewareManager
{
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
        return DocumentAST::parse($request)
            ->operations()
            ->map(function (OperationDefinitionNode $node) {
                $operationType = $node->operation;
                $fieldNames = collect($node->selectionSet->selections)->map(function (SelectionNode $selectionNode){
                    return $selectionNode->name->value;
                })->toArray();

                return $this->operation($operationType, $fieldNames);
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
     */
    public function registerMutation($name, array $middleware)
    {
        $this->mutations = array_merge($this->mutations, [$name => $middleware]);
    }

    /**
     * Get middleware for operation.
     *
     * @param string $operation
     * @param array  $fieldNames
     *
     * @return array
     */
    public function operation($operation, array $fieldNames)
    {
        if ('mutation' === $operation) {
            return array_collapse(array_map(function ($fieldName) {
                return $this->mutation($fieldName);
            }, $fieldNames));
        } elseif ('query' === $operation) {
            return array_collapse(array_map(function ($field) {
                return $this->query($field);
            }, $fieldNames));
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
        dd('here');
    }
}
