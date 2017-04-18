<?php

namespace Nuwave\Lighthouse\Support\Interfaces;

use GraphQL\Type\Definition\ResolveInfo;

interface Connection
{
    /**
     * Get the name of the connection.
     * Note: Connection names must be unique.
     *
     * @return string
     */
    public function name();

    /**
     * Get the type of connection.
     *
     * @return string
     */
    public function type();

    /**
     * Get available arguments for connection.
     *
     * @return array
     */
    public function args();

    /**
     * Resolve connection.
     *
     * @param  mixed       $parent
     * @param  array       $args
     * @param  mixed       $context
     * @param  ResolveInfo $info
     * @return mixed
     */
    public function resolve($parent, array $args, $context, ResolveInfo $info);
}
