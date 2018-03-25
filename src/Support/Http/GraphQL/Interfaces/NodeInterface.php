<?php

namespace Nuwave\Lighthouse\Support\Http\GraphQL\Interfaces;

use Nuwave\Lighthouse\Support\Traits\HandlesGlobalId;

class NodeInterface
{
    use HandlesGlobalId;

    /**
     * Resolve node value.
     *
     * @param string $value
     *
     * @return mixed
     */
    public function resolve($value)
    {
        return graphql()->nodes()->resolveType($value);
    }
}
