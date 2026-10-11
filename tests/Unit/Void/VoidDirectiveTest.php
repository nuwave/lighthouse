<?php declare(strict_types=1);

namespace Tests\Unit\Void;

use Tests\TestCase;

final class VoidDirectiveTest extends TestCase
{
    public function testVoid(): void
    {
        $this->schema = /** @lang GraphQL */ '
        type Query {
            foo: _ @void
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
