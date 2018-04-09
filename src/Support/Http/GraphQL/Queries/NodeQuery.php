<?php

namespace Nuwave\Lighthouse\Support\Http\GraphQL\Queries;

use Nuwave\Lighthouse\Support\Traits\HandlesGlobalId;

class NodeQuery
{
    use HandlesGlobalId;

    /**
     * Resolve node.
     *
     * @param mixed $root
     * @param array $args
     *
     * @return string
     */
    public function resolve($root, array $args)
    {
        return graphql()->nodes()->resolve($args['id']);
    }
}
