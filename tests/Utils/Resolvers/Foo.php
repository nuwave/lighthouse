<?php

namespace Tests\Utils\Resolvers;

use Illuminate\Support\Arr;
use GraphQL\Type\Definition\ResolveInfo;

class Foo
{
    public function bar($root, array $args, $context = null, ResolveInfo $info = null)
    {
        return 'foo.bar';
    }

    public function baz($root, array $args, $context = null, ResolveInfo $info = null)
    {
        return Arr::get($args, 'directive.0');
    }
}
