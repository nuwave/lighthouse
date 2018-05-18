<?php


namespace Tests\Unit\Schema;


use Tests\TestCase;

class ErrorsTest extends TestCase
{
    /** @test */
    public function it_can_show_error_when_query_not_found()
    {
        $schema = '
        type Query {
            me: String
        }';

        $result = $this->execute($schema, '{ InvalidQuery }');
        $this->assertEquals(
            "Cannot query field \"InvalidQuery\" on type \"Query\".",
            $result->toArray()['errors'][0]['message']
        );
    }
}