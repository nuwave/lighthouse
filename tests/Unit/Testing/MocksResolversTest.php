<?php

namespace Tests\Unit\Testing;

use Tests\TestCase;

class MocksResolversTest extends TestCase
{
    public function testCallsMock(): void
    {
        $this->mockResolver()
            ->with(null, [
                'foo' => 1,
            ])
            ->willReturn(2);

        $this->schema = /** @lang GraphQL */ '
        type Query {
            foo(foo: Int): Int @mock
        }
        ';

        $this->graphQL(/** @lang GraphQL */ '
        {
            foo(foo: 1)
        }
        ')->assertExactJson([
            'data' => [
                'foo' => 2,
            ],
        ]);
    }

    public function testCustomExpects(): void
    {
        $this->mockResolverExpects(
            $this->never()
        );
    }
}
