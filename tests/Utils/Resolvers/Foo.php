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
     * @param  array<string, mixed>  $args
     * @see \Tests\Unit\Schema\Directives\FieldDirectiveTest
     */
    public function baz($root, array $args): string
    {
        return Arr::get($args, 'directive.0');
    }
}
