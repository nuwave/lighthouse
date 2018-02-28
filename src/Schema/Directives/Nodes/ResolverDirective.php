<?php

namespace Nuwave\Lighthouse\Schema\Directives\Nodes;

use Nuwave\Lighthouse\Support\Contracts\GlobalIdResolver;

class ResolverDirective implements GlobalIdResolver
{
    /**
     * Directive name.
     *
     * @return string
     */
    public function name()
    {
        return 'resolver';
    }

    /**
     * Resolve node by global id.
     *
     * @param mixed  $id
     * @param string $globalId
     *
     * @return mixed
     */
    public function resolve($id, $globalId)
    {
        // TODO: Resolve type by id.
    }
}
