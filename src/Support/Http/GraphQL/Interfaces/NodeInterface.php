<?php

namespace Nuwave\Lighthouse\Support\Http\GraphQL\Interfaces;

use Nuwave\Lighthouse\Schema\NodeRegistry;

/**
 * @deprecated in favour of NodeRegistry
 */
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
        return resolve(NodeRegistry::class)->resolveType($value);
    }
}
