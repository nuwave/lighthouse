<?php

namespace Nuwave\Lighthouse\Support\Contracts;

use GraphQL\Type\Definition\ResolveInfo;

interface Mutation
{
    /**
     * Resolve the mutation.
     *
     * @param mixed            $root
     * @param array            $args
     * @param mixed            $context
     * @param ResolveInfo|null $info
     *
     * @return mixed
     */
    public function resolve($root, array $args, $context = null, ResolveInfo $info = null);
}
