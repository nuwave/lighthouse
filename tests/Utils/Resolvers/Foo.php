<?php declare(strict_types=1);

namespace Tests\Utils\Resolvers;

final class Foo
{
    public function __invoke(): string
    {
        return 'foo.baz';
    }

    public function bar(): string
    {
        return 'foo.bar';
    }
}
