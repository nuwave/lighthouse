<?php

namespace Nuwave\Lighthouse\Schema;

use GraphQL\Utils\AST;
use Nuwave\Lighthouse\Schema\AST\DocumentAST;
use GraphQL\Language\AST\OperationDefinitionNode;

class MiddlewareRegistry
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
    public function forRequest(string $request): array
    {
        $document = DocumentAST::fromSource($request);
        $fragments = $document->fragmentDefinitions();

        return $document->operationDefinitions()
            ->map(function (OperationDefinitionNode $node) use ($fragments) {
                $definition = AST::toArray($node);
                $operation = array_get($definition, 'operation');
                $fields = array_map(function ($selection) use ($fragments) {
                    $field = array_get($selection, 'name.value');

                    if ('FragmentSpread' === array_get($selection, 'kind')) {
                        $fragment = $fragments->first(function ($def) use ($field) {
                            return data_get($def, 'name.value') === $field;
                        });

                        return array_pluck(
                            data_get($fragment, 'selectionSet.selections', []),
                            'name.value'
                        );
                    }

                    return [$field];
                }, array_get($definition, 'selectionSet.selections', []));

                return $this->operation($operation, array_unique(array_flatten($fields)));
            })
            ->collapse()
            ->unique()
            ->toArray();
    }

    /**
     * Register query middleware.
     *
     * @param string $name
     * @param array $middleware
     *
     * @return MiddlewareRegistry
     */
    public function registerQuery(string $name, array $middleware): MiddlewareRegistry
    {
        $this->queries = array_merge($this->queries, [$name => $middleware]);

        return $this;
    }

    /**
     * Register mutation middleware.
     *
     * @param string $name
     * @param array $middleware
     *
     * @return MiddlewareRegistry
     */
    public function registerMutation($name, array $middleware): MiddlewareRegistry
    {
        $this->mutations = array_merge($this->mutations, [$name => $middleware]);

        return $this;
    }

    /**
     * Get middleware for operation.
     *
     * @param string $operation
     * @param array  $fields
     *
     * @return array
     */
    public function operation(string $operation, array $fields): array
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
    public function query(string $name): array
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
    public function mutation(string $name): array
    {
        return array_get($this->mutations, $name, []);
    }
}
