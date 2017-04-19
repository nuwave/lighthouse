<?php

namespace Nuwave\Lighthouse\Tests\Support\GraphQL\Connections;

use GraphQL\Type\Definition\ResolveInfo;
use Nuwave\Lighthouse\Support\Interfaces\Connection;

class UserConnection implements Connection
{
    /**
     * Get the name of the connection.
     * Note: Connection names must be unique.
     *
     * @return string
     */
    public function name()
    {
        return 'UserConnection';
    }

    /**
     * Get name of connection.
     *
     * @return string
     */
    public function type()
    {
        return 'user';
    }

    /**
     * Available connection arguments.
     *
     * @return array
     */
    public function args()
    {
        return [];
    }

    /**
     * Resolve connection.
     *
     * @param  mixed  $parent
     * @param  array  $args
     * @param  mixed  $context
     * @param  ResolveInfo $info
     * @return mixed
     */
    public function resolve($parent, array $args, $context, ResolveInfo $info)
    {
        return $parent->users()->getConnection($args);
    }
}
