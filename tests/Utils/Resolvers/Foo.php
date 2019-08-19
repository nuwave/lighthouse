<?php

namespace Tests\Utils\Resolvers;

use Illuminate\Support\Arr;

class Foo
{
    public function __invoke(): string
    {
        return 'foo.baz';
    }

    public function bar(): string
    {
        return 'foo.bar';
    }

    /**
     * @param  mixed  $root
     * @param  mixed[]  $args
     * @return mixed
     */
    public function baz($root, array $args)
    {
        return Arr::get($args, 'directive.0');
    }
}
