<?php

namespace Nuwave\Lighthouse\Execution;

use GraphQL\Type\Definition\ResolveInfo;
use Nuwave\Lighthouse\Schema\Context;
use Nuwave\Lighthouse\Support\Contracts\GraphQLContext;

interface Resolver
{
    public function __invoke($root, $args, GraphQLContext $context, ResolveInfo $resolveInfo);
}
