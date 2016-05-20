<?php

namespace Nuwave\Relay\Tests;

use GraphQL\Type\Definition\ObjectType;
use GraphQL\Type\Definition\Type;

class GraphQLSchemaTest extends TestCase
{
    /**
     * @test
     */
    public function itCanManuallyAddTypeToSchema()
    {
        $graphql = app('graphql');

        $graphql->addType('foo', 'bar');

        $this->assertCount(1, $graphql->types());
        $this->assertEquals($graphql->types()->get('bar'), 'foo');
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

        $this->assertCount(1, $graphql->types());
        $this->assertEquals($graphql->types()->get('Human'), $type);
    }
}
