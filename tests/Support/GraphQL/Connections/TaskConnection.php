<?php

namespace Nuwave\Lighthouse\Tests\Support\GraphQL\Connections;

use GraphQL\Type\Definition\ResolveInfo;
use Nuwave\Lighthouse\Tests\Support\Models\Task;
use Nuwave\Lighthouse\Support\Interfaces\Connection;

class TaskConnection implements Connection
{
    /**
     * Get the name of the connection.
     * Note: Connection names must be unique.
     *
     * @return string
     */
    public function name()
    {
        return 'TaskConnection';
    }

    /**
     * Get name of connection.
     *
     * @return string
     */
    public function type()
    {
        return 'task';
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
        return Task::whereHas('user', function ($query) use ($parent) {
            $query->where('id', $parent->id);
        })->getConnection($args);
    }
}
