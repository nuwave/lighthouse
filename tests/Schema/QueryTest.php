<?php

namespace Nuwave\Relay\Tests\Schema;

use Nuwave\Relay\Tests\TestCase;

class QueryTest extends TestCase
{
    /**
     * @test
     */
    public function itCanManuallyAddQueryToSchema()
    {
        $graphql = app('graphql');

        $graphql->addQuery('foo', 'bar');

        $this->assertEquals($graphql->getQuery('bar'), 'foo');
    }
}
