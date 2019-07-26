<?php

namespace Nuwave\Lighthouse\Execution\Arguments;

use Nuwave\Lighthouse\Support\Contracts\GraphQLContext;

interface ResolveNestedAfter
{
    public function resolveBefore($root, $value, GraphQLContext $context);
}
