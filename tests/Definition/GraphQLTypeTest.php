<?php

namespace Nuwave\Relay\Tests\Definition;

use GraphQL\Type\Definition\ObjectType;
use Nuwave\Relay\Tests\TestCase;
use Nuwave\Relay\Tests\Support\Models\User;
use Nuwave\Relay\Tests\Support\GraphQL\Types\UserType;
use Nuwave\Relay\Tests\Support\GraphQL\Types\TaskType;

class GraphQLTypeTest extends TestCase
{
    /**
     * @test
     */
    public function itCanTransformToType()
    {
        app('graphql')->schema()->type('task', TaskType::class);
        
        $userType = new UserType;
        $type = $userType->toType();

        $this->assertEquals('User', $type->name);
        $this->assertInstanceOf(ObjectType::class, $type);
    }
}
