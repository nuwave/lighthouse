<?php

namespace Nuwave\Relay\Support\Definition;

class NodeType extends GraphQLInterface
{
    /**
     * Interface attributes.
     *
     * @var array
     */
    protected $attributes = [
        'name' => 'Node',
        'description' => 'An object with an ID.'
    ];

    /**
     * Available fields on type.
     *
     * @return array
     */
    public function fields()
    {
        return [
            'id' => [
                'type' => Type::nonNull(Type::id()),
                'description' => 'The id of the object.'
            ]
        ];
    }

    /**
     * Resolve the interface.
     *
     * @param  mixed $obj
     * @return mixed
     */
    public function resolveType($obj)
    {
        return GraphQL::type($this->extractType($obj));
    }

    /**
     * Extract type.
     *
     * @param  mixed $obj
     * @return mixed
     */
    protected function extractType($obj)
    {
        if (is_array($obj)) {
            return $obj['graphqlType'];
        }

        return $obj->graphqlType;
    }
}
