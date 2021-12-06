<?php

namespace Nuwave\Lighthouse\Federation\Resolvers;

use GraphQL\Type\Definition\ResolveInfo;
use Nuwave\Lighthouse\Federation\FederationPrinter;
use Nuwave\Lighthouse\Support\Contracts\GraphQLContext;

class Service
{
    /**
     * @param  array<string, mixed>  $args  Always empty
     *
     * @return array{sdl: string}
     */
    public function __invoke($root, array $args, GraphQLContext $context, ResolveInfo $resolveInfo): array
    {
        return [
            'sdl' => FederationPrinter::print($resolveInfo->schema),
        ];
    }
}
