<?php

namespace Nuwave\Lighthouse\Schema\Generators;

use GraphQL\Type\Definition\ObjectType;
use Nuwave\Lighthouse\Support\Definition\EdgeType;

class EdgeTypeGenerator
{
    /**
     * Generate a new edge type.
     *
     * @param  string  $name
     * @param  \GraphQL\Type\Definition\ObjectType  $type
     * @return \GraphQL\Type\Definition\ObjectType
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
