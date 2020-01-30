<?php

namespace Nuwave\Lighthouse\Federation\Resolvers;

use GraphQL\Type\Definition\ResolveInfo;
use Nuwave\Lighthouse\Support\Contracts\GraphQLContext;

class Entity
{
    public function resolve($rootValue, array $args, GraphQLContext $context, ResolveInfo $resolveInfo)
    {
        dd('_Entity resolver'); // TODO Figure out what to do here :)
        dd($rootValue, $args);
    }
}
