<?php

namespace Nuwave\Relay\Tests\Schema;

use GraphQL\Type\Definition\ObjectType;
use GraphQL\Type\Definition\Type;
use Nuwave\Relay\Tests\TestCase;
use Nuwave\Relay\Tests\Support\GraphQL\Types\UserType;

class TypeTest extends TestCase
{
    /**
     * @test
     */
    public function itCanManuallyAddTypeToSchema()
    {
        $graphql = app('graphql');

        $graphql->addType('foo', 'bar');

        $this->assertEquals($graphql->getType('bar'), 'foo');
    }

    /**
     * @test
     */
    public function itCanManuallyAddTypeClassToSchema()
    {
        $graphql = app('graphql');

        $type = new ObjectType([
            'name' => 'Human',
            'description' => 'foo',
            'fields' => [
                'id' => [
                    'type' => Type::nonNull(Type::int()),
                    'description' => 'The id of the human.',
                ],
                'name' => [
                    'type' => Type::string(),
                    'description' => 'The naem of the human.'
                ]
            ]
        ]);

        $graphql->addType($type);

        $this->assertEquals($graphql->getType('Human'), $type);
    }

    /**
     * @test
     */
    public function itCanResolveTypeInstance()
    {
        $graphql = app('graphql');

        $type = new UserType;
        $graphql->addType($type);
        $resolvedType = $graphql->type('User');

        $this->assertInstanceOf(ObjectType::class, $resolvedType);
        $this->assertSame($resolvedType, $graphql->type('User'));
    }

    /**
     * @test
     */
    public function itThrowsExceptionIfTypeIsNotFound()
    {
        $graphql = app('graphql');

        $this->setExpectedException(\Exception::class);

        $graphql->type('User');
    }
}
