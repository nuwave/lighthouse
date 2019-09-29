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

        $this->schema = '
        type Query {
            foo(foo: Int): Int @mock
        }
        ';

        $this->graphQL('
        {
            foo(foo: 1)
        }
        ')->assertExactJson([
            'data' => [
                'foo' => 2,
            ],
        ]);
    }
}
