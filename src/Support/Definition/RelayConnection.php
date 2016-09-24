<?php

namespace Nuwave\Lighthouse\Support\Definition;

use GraphQL\Type\Definition\ResolveInfo;

abstract class RelayConnection
{
    /**
     * Convert connection to field.
     *
     * @return array
     */
    public function toArray()
    {
        return [];
    }

    /**
     * Get name of connection.
     *
     * @return string
     */
    abstract public function name();

    /**
     * Get available arguments for connection.
     *
     * @return array
     */
    abstract public function args();

    /**
     * Resolve connection.
     *
     * @param  mixed       $parent
     * @param  array       $args
     * @param  mixex       $context
     * @param  ResolveInfo $info
     * @return mixed
     */
    abstract public function resolve($parent, array $args, $context, ResolveInfo $info);
}
