<?php

namespace Nuwave\Relay\Tests\Queries;

use Nuwave\Relay\Tests\TestCase;

class MutationTest extends TestCase
{
    /**
     * @test
     */
    public function itCanManuallyAddQueryToSchema()
    {
        $graphql = app('graphql');

        $graphql->addMutation('foo', 'bar');

        $this->assertEquals($graphql->getMutation('bar'), 'foo');
    }
}
