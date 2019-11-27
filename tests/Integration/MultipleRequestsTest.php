<?php

namespace Tests\Integration;

use Tests\TestCase;

class MultipleRequestsTest extends TestCase
{
    public function testCanFireMultipleRequestsInOneTest(): void
    {
        $this->schema = '
        type Query {
            return(this: String!): String @field(resolver:"'.$this->qualifyTestResolver().'")
        }
        ';

        $this->graphQL('
        {
            return(this: "foo")
        }
        ')->assertJson([
            'data' => [
                'return' => 'foo',
            ],
        ]);

        $this->graphQL('
        {
            return(this: "bar")
        }
        ')->assertJson([
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
