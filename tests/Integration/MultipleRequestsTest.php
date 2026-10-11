<?php declare(strict_types=1);

namespace Tests\Integration;

use Tests\TestCase;

final class MultipleRequestsTest extends TestCase
{
    public function testFireMultipleRequestsInOneTest(): void
    {
        $this->mockResolver(static fn ($_, array $args): string => $args['this']);

        $this->schema = /** @lang GraphQL */ <<<'GRAPHQL'
        type Query {
            return(this: String!): String @mock
        }
        GRAPHQL;

        $this->graphQL(/** @lang GraphQL */ <<<'GRAPHQL'
        {
            return(this: "foo")
        }
        GRAPHQL)->assertExactJson([
            'data' => [
                'return' => 'foo',
            ],
        ]);

        $this->graphQL(/** @lang GraphQL */ <<<'GRAPHQL'
        {
            return(this: "bar")
        }
        GRAPHQL)->assertExactJson([
            'data' => [
                'return' => 'bar',
            ],
        ]);
    }
}
