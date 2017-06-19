<?php

namespace Nuwave\Lighthouse\Support\Definition;

use GraphQL\Type\Definition\Type;
use GraphQL\Type\Definition\ObjectType;

class EdgeType extends GraphQLType
{
    /**
     * The name of the edge (i.e. `User`).
     *
     * @var string
     */
    protected $name = '';

    /**
     * The type of edge.
     *
     * @var mixed
     */
    protected $type;

    /**
     * Special fields present on this connection type.
     *
     * @param        $name
     * @param string $type
     */
    public function __construct($name, $type)
    {
        parent::__construct();

        $this->name = $name;
        $this->type = $type;
    }

    /**
     * Fields that exist on every connection.
     *
     * @return array
     */
    public function fields()
    {
        return [
            'node' => [
                'type' => $this->type,
                'description' => 'The item at the end of the edge.',
                'resolve' => function ($edge) {
                    return $edge;
                },
            ],
            'cursor' => [
                'type' => Type::nonNull(Type::string()),
                'description' => 'A cursor for use in pagination.',
                'resolve' => function ($edge) {
                    if (is_array($edge) && isset($edge['relayCursor'])) {
                        return $edge['relayCursor'];
                    } elseif (is_array($edge->attributes)) {
                        return $edge->attributes['relayCursor'];
                    }

                    return $edge->relayCursor;
                },
            ],
        ];
    }

    /**
     * Convert the Fluent instance to an array.
     *
     * @return array
     */
    public function toArray()
    {
        $name = preg_replace('/Edge$/i', '', $this->name).'Edge';

        return [
            'name' => studly_case($name),
            'description' => 'An edge in a connection.',
            'fields' => $this->fields(),
        ];
    }

    /**
     * Create the instance of the connection type.
     *
     * @return ObjectType
     */
    public function toType()
    {
        return new ObjectType($this->toArray());
    }
}
