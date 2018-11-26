<?php

namespace Nuwave\Lighthouse\Support\Contracts;

use GraphQL\Type\Definition\ResolveInfo;

interface HasResolverArguments
{
    /**
     * Get all the resolver arguments.
     *
     * @return array
     */
    public function resolverArguments(): array;

    /**
     * @param $root
     * @param array       $args
     * @param null        $context
     * @param ResolveInfo $resolveInfo
     *
     * @return static
     */
    public function setResolverArguments($root, array $args, $context = null, ResolveInfo $resolveInfo);
}
