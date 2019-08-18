<?php

namespace Nuwave\Lighthouse\Execution\Arguments;

use Nuwave\Lighthouse\Support\Contracts\GraphQLContext;

interface ResolveNestedAfter
{
    public function resolveAfter($root, $args, GraphQLContext $context);
}
