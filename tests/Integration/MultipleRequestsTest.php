<?php

namespace Tests\Integration;

use Tests\TestCase;

class MultipleRequestsTest extends TestCase
{
    /**
     * @test
     */
    public function itCanFireMultipleRequestsInOneTest(): void
    {
        $this->schema = '
        type Query {
            return(this: String!): String @field(resolver:"'.$this->qualifyTestResolver().'")
        }
        ';

        $this->query('
        {
            return(this: "foo")
        }
        ')->assertJson([
            'data' => [
                'return' => 'foo',
            ],
        ]);

        $this->query('
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
