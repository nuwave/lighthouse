<?php

namespace Nuwave\Lighthouse\Support\Interfaces;

use GraphQL\Type\Definition\ResolveInfo;

interface Connection
{
    /**
     * Get name of connection.
     *
     * @return string
     */
    public function name();

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
     * @param  ResolveInfo $info
     * @return mixed
     */
    public function resolve($parent, array $args, ResolveInfo $info);
}
