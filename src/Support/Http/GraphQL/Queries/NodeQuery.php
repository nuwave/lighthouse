<?php

namespace Nuwave\Lighthouse\Support\Http\GraphQL\Queries;

use Nuwave\Lighthouse\Schema\NodeRegistry;

/**
 * @deprecated in favour of NodeRegistry
 */
class NodeQuery
{
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
        return resolve(NodeRegistry::class)->resolve($args['id']);
    }
}
