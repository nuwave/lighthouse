<?php

namespace Nuwave\Lighthouse\Schema\Directives\Nodes;

use Nuwave\Lighthouse\Schema\Resolvers\ScalarResolver;
use Nuwave\Lighthouse\Schema\Values\NodeValue;
use Nuwave\Lighthouse\Support\Contracts\NodeResolver;

class ScalarDirective implements NodeResolver
{
    /**
     * Name of the directive.
     *
     * @var string
     * @return string
     */
    public function name()
    {
        return 'scalar';
    }

    public function resolve($value)
    {
        return ScalarResolver::resolve($value);
    }
}
