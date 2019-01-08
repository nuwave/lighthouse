<?php

namespace Nuwave\Lighthouse\Support\Contracts;

use Nuwave\Lighthouse\Schema\Values\NodeValue;

interface NodeMiddleware extends Directive
{
    /**
     * Handle node value.
     *
     * @param  \GraphQL\Language\AST\NodeValue  $value
     * @param  \Closure  $next
     *
     * @return \GraphQL\Language\AST\NodeValue
     */
    public function handleNode(NodeValue $value, \Closure $next);
}
