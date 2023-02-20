<?php

namespace Tests\Integration;

use Tests\TestCase;

final class MultipleRequestsTest extends TestCase
{
    public function testFireMultipleRequestsInOneTest(): void
    {
        $this->mockResolver(function ($root, array $args): string {
            return $args['this'];
        });

        $this->schema = /** @lang GraphQL */ '
        type Query {
            return(this: String!): String @mock
        }
        ';

        $this->graphQL(/** @lang GraphQL */ '
        {
            return(this: "foo")
        }
        ')->assertExactJson([
            'data' => [
                'return' => 'foo',
            ],
        ]);

        $this->graphQL(/** @lang GraphQL */ '
        {
            return(this: "bar")
        }
        ')->assertExactJson([
            'data' => [
                'return' => 'bar',
            ],
        ]);
    }
}
