<?php

namespace Nuwave\Lighthouse\Tests\Support\GraphQL\Connections;

use GraphQL\Type\Definition\ResolveInfo;
use Nuwave\Lighthouse\Support\Interfaces\Connection;
use Nuwave\Lighthouse\Tests\Support\Models\Task;

class TaskConnection implements Connection
{

    /**
     * get the name of the connection. if this is
     * null it will default to the full classname including namespaces
     * @return string
     */
    public function name()
    {
        return "TaskConnection";
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
     * @param  ResolveInfo $info
     * @return mixed
     */
    public function resolve($parent, array $args, ResolveInfo $info)
    {
        return Task::whereHas('user', function ($query) use ($parent) {
            $query->where('id', $parent->id);
        })->getConnection($args);
    }

}
