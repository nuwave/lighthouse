<?php declare(strict_types=1);

namespace Tests\Unit\Void;

use Tests\TestCase;

final class VoidDirectiveTest extends TestCase
{
    public function testVoid(): void
    {
        $this->mockResolverExpects($this->once())
            ->willReturn(null);

        $this->schema = /** @lang GraphQL */ '
        type Query {
            foo: _ @mock @void
        }
        ';

        $this->graphQL(/** @lang GraphQL */ '
        {
            foo
        }
        ')->assertJson([
            'data' => [
                'foo' => 'VOID',
            ],
        ]);
    }

    public function testVoidReplacesNonNullResult(): void
    {
        $this->mockResolver('ignored');

        $this->schema = /** @lang GraphQL */ '
        type Query {
            foo: _ @mock @void
        }
        ';

        $this->graphQL(/** @lang GraphQL */ '
        {
            foo
        }
        ')->assertJson([
            'data' => [
                'foo' => 'VOID',
            ],
        ]);
    }
}
