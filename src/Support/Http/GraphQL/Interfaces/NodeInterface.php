<?php

namespace Nuwave\Lighthouse\Support\Http\GraphQL\Interfaces;

class NodeInterface
{
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
