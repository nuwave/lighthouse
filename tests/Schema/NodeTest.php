<?php

namespace Nuwave\Relay\Tests\Schema;

use Nuwave\Relay\Tests\TestCase;

class NodeTest extends TestCase
{
    /**
     * @test
     */
    public function itAutomaticallyRegistersNodeType()
    {
        $graphql = app('graphql');
        $nodeType = $graphql->getType('node');
    }
}
