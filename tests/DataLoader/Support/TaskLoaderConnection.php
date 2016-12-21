<?php

namespace Nuwave\Lighthouse\Tests\DataLoader\Support;

use GraphQL\Type\Definition\ResolveInfo;

class TaskLoaderConnection extends TaskFetcherConnection
{
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
        return dataFetcher('task')->load($parent->id, $parent, $args);
    }
}
