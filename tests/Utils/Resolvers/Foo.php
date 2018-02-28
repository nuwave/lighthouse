<?php

namespace Nuwave\Lighthouse\Tests\Utils\Resolvers;

use GraphQL\Type\Definition\ResolveInfo;

class Foo
{
    public function bar($root, array $args, $context = null, ResolveInfo $info = null)
    {
        return 'foo.bar';
    }
}
