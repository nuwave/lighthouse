<?php

namespace Nuwave\Lighthouse\Schema\Generators;

use Nuwave\Lighthouse\Support\Definition\EdgeType;
use GraphQL\Type\Definition\ObjectType;

class EdgeTypeGenerator
{
    /**
     * Generate a new edge type.
     *
     * @param string $name
     * @param ObjectType $type
     * @return ObjectType
     */
    public function build($name, ObjectType $type)
    {
        if (preg_match('/Connection$/', $name)) {
            $name = substr($name, 0, strlen($name) - 10);
        }

        $edge = new EdgeType($name, $type);

        return $edge->toType();
    }
}
