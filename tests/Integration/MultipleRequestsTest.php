<?php

namespace Tests\Integration;

use Tests\TestCase;

class MultipleRequestsTest extends TestCase
{
    public function testCanFireMultipleRequestsInOneTest(): void
    {
        $this->schema = /* @lang GraphQL */'
        type Query {
            return(this: String!): String @field(resolver:"'.$this->qualifyTestResolver().'")
        }
        ';

        $this->graphQL(/* @lang GraphQL */ '
        {
            return(this: "foo")
        }
        ')->assertExactJson([
            'data' => [
                'return' => 'foo',
            ],
        ]);

        $this->graphQL(/* @lang GraphQL */ '
        {
            return(this: "bar")
        }
        ')->assertExactJson([
           'data' => [
               'return' => 'bar',
           ],
        ]);
    }

    public function resolve($root, array $args): string
    {
        return $args['this'];
    }
}
