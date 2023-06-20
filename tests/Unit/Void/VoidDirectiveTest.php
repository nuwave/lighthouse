<?php

namespace Tests\Unit\Void;

use Nuwave\Lighthouse\Void\VoidServiceProvider;
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
                'foo' => VoidServiceProvider::UNIT,
            ],
        ]);
    }
}
