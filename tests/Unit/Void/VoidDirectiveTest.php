<?php

namespace Tests\Unit\Void;

use Tests\TestCase;

class VoidDirectiveTest extends TestCase
{
    public function testVoid(): void
    {
        $this->schema = /** @lang GraphQL */ '
        type Query {
            foo: Int! @void
        }
        ';

        $this->graphQL(/** @lang GraphQL */ '
        {
            foo
        }
        ')->assertExactJson([
            'data' => [
                'foo' => null,
            ],
        ]);
    }

    public function testUseNull()
    {
        
    }
}
