<?php

namespace Nuwave\Lighthouse\Execution\Arguments;

use Nuwave\Lighthouse\Support\Contracts\GraphQLContext;

interface AfterResolver
{
    public function resolve($root, $value, GraphQLContext $context);
}
