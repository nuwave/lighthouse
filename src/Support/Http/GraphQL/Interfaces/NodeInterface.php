<?php

namespace Nuwave\Lighthouse\Support\Http\GraphQL\Interfaces;

use Nuwave\Lighthouse\Schema\NodeRegistry;
use Nuwave\Lighthouse\Support\Traits\HandlesGlobalId;

/**
 * @deprecated in favour of NodeRegistry
 */
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
        return $this->resolve(NodeRegistry::class)->resolveType($value);
    }
}
