<?php

namespace Nuwave\Lighthouse\Schema\Generators;

use GraphQL\Language\AST\Field;
use Nuwave\Lighthouse\Schema\Connection;

class ConnectionGenerator
{
    /**
     * Current depth.
     *
     * @var int
     */
    protected $depth = 0;

    /**
     * Current path;.
     *
     * @var array
     */
    protected $path = [];

    /**
     * Relay edge names.
     *
     * @var array
     */
    protected $relayEdges = [
        'pageInfo', 'edges', 'node',
    ];

    /**
     * Colleciton of connections.
     *
     * @var \Illuminate\Support\Collection
     */
    protected $connections;

    /**
     * Create new instance of connection generator.
     */
    public function __construct()
    {
        $this->connections = collect();
    }

    /**
     * Build collection of connections from request.
     *
     * @param  array  $selectionSet
     * @param  string $root
     * @return array
     */
    public function build(array $selectionSet, $root = '')
    {
        $this->extractConnections($selectionSet, $root);

        return $this->connections->toArray();
    }

    /**
     * Parse arguments.
     *
     * @param  array $selectionSet
     * @param  string $root
     * @return void
     */
    protected function extractConnections(array $selectionSet = [], $root = '')
    {
        foreach ($selectionSet as $field) {
            if ($this->hasChildren($field)) {
                $name = $field->name->value;

                if (! $this->isEdge($name)) {
                    $this->path[] = $name;

                    $key = implode('.', $this->path);

                    $connection = new Connection([
                        'name' => $name,
                        'root' => $root,
                        'path' => $key,
                        'arguments' => $field->arguments ? $this->getArguments($field->arguments) : [],
                    ]);

                    $this->connections->put($key, $connection);
                }

                $this->extractConnections($field->selectionSet->selections, $root);
            }
        }

        array_pop($this->path);
    }

    /**
     * Determine if field has selection set.
     *
     * @param  Field   $field
     * @return bool
     */
    protected function hasChildren($field)
    {
        return $this->isField($field) && isset($field->selectionSet) && ! empty($field->selectionSet->selections);
    }

    /**
     * Determine if name is a relay edge.
     *
     * @param  string  $name
     * @return bool
     */
    protected function isEdge($name)
    {
        return in_array($name, $this->relayEdges);
    }

    /**
     * Determine if selection is a Field.
     *
     * @param  mixed  $selection
     * @return bool
     */
    public function isField($selection)
    {
        return is_object($selection) && $selection instanceof Field;
    }

    /**
     * Set arguments of selection.
     *
     * @param array $arguments
     * @return array
     */
    public function getArguments(array $arguments)
    {
        return collect($arguments)->flatMap(function ($argument) {
            return [$argument->name->value => $argument->value->value];
        })->toArray();
    }
}
