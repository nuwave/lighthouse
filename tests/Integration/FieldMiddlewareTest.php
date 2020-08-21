<?php

namespace Tests\Integration;

use Tests\TestCase;

class FieldMiddlewareTest extends TestCase
{
    public function testTransformsArgsBeforeCustomFieldMiddleware(): void
    {
        $this->schema = /** @lang GraphQL */ '
        type Query {
            foo(id: ID! @trim): Foo @customFieldMiddleware
        }

        type Foo {
            id: String!
        }
        ';

        $this->graphQL(/** @lang GraphQL */ '
        {
            foo(id: "   foo   ") {
                id
            }
        }
        ')->assertExactJson([
            'data' => [
                'foo' => [
                    'id' => 'foo',
                ],
            ],
        ]);
    }
}
