<?php

namespace Nuwave\Lighthouse\Tests\Schema\Generators;

use Nuwave\Lighthouse\Tests\TestCase;
use Nuwave\Lighthouse\Tests\Support\GraphQL\Types\UserType;

class EdgeTypeGeneratorTest extends TestCase
{
    /**
     * @test
     */
    public function itCanGenerateEdgeType()
    {
        app('graphql')->schema()->type('user', UserType::class);

        $type = app('graphql')->type('user');
        $edge = app('graphql')->schema()->edgeInstance('user', $type);
        $this->assertEquals('UserEdge', $edge->name);
        $this->assertContains('node', array_keys($edge->config['fields']));
        $this->assertContains('cursor', array_keys($edge->config['fields']));
        $this->assertSame($edge, app('graphql')->edge('user'));
    }
}
