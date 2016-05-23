<?php

namespace Nuwave\Relay\Tests\Schema;

use Nuwave\Relay\Tests\TestCase;
use GraphQL\Type\Definition\ObjectType;

class SchemaBuilderTest extends TestCase
{
    /**
     * @test
     */
    public function itCanGroupElementsByNamespace()
    {
        $graphql = app('graphql');
        $namespace = 'Nuwave\\Relay\\Tests\\Support\\GraphQL\\Types';

        $graphql->schema()->group(['namespace' => $namespace], function () use ($graphql) {
            $graphql->schema()->type('userGrouped', 'UserType');
            $graphql->schema()->type('taskGrouped', 'TaskType');
        });

        $this->assertInstanceOf(ObjectType::class, $graphql->type('userGrouped'));
        $this->assertInstanceOf(ObjectType::class, $graphql->type('taskGrouped'));
    }
}
