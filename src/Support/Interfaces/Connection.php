<?php

namespace Nuwave\Lighthouse\Support\Interfaces;

use GraphQL\Type\Definition\ResolveInfo;

interface Connection
{


    /**
     * get the name of the connection. if this is
     * null it will default to the full classname including namespaces
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
     * @param  ResolveInfo $info
     * @return mixed
     */
    public function resolve($parent, array $args, ResolveInfo $info);
}
