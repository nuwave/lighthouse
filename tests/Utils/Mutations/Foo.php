<?php

namespace Nuwave\Lighthouse\Tests\Utils\Mutations;

use GraphQL\Type\Definition\ResolveInfo;
use Nuwave\Lighthouse\Support\Contracts\Mutation;

class Foo implements Mutation
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
    public function resolve($root, array $args, $context = null, ResolveInfo $info = null)
    {
        return array_get($args, 'bar').' '.array_get($args, 'baz');
    }
}
