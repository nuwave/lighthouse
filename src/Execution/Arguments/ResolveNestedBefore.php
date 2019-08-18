<?php

namespace Nuwave\Lighthouse\Execution\Arguments;

use Nuwave\Lighthouse\Support\Contracts\GraphQLContext;

interface ResolveNestedBefore
{
    public function resolveBefore($root, $args, GraphQLContext $context);
}
