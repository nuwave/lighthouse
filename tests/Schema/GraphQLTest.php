<?php

namespace Nuwave\Relay\Tests\Schema;

use Illuminate\Support\Arr;
use Nuwave\Relay\Tests\TestCase;
use GraphQL\Type\Definition\ObjectType;
use GraphQL\Type\Definition\Type;

class GraphQLTest extends TestCase
{
    /**
     * @test
     * @group failing
     */
    public function itCanGenerateASchema()
    {
        $type = $this->getObjectType();
        $queryType = $this->getQueryType($type);
        $mutationType = $this->getMutationType($type);
        $graphql = app('graphql');

        $graphql->addQuery($queryType, 'humanQuery');
        $graphql->addMutation($mutationType, 'fooMutation');

        $schema = app('graphql')->buildSchema();
        $schemaQueries = $schema->getQueryType();
        $mutationQueries = $schema->getMutationType();

        $this->assertTrue($this->hasType($schemaQueries, 'HumanQuery'));
        $this->assertTrue($this->hasType($mutationQueries, 'FooMutation'));
    }

    /**
     * Object contains field type.
     *
     * @param  ObjectType $type
     * @param  string     $name
     * @return boolean
     */
    protected function hasType(ObjectType $type, $name)
    {
        return collect($type->config['fields'])->contains(function ($i, $type) use ($name) {
            return $type instanceof ObjectType && $type->name === $name;
        });
    }

    /**
     * Generate object type.
     *
     * @return ObjectType
     */
    protected function getObjectType()
    {
        return new ObjectType([
            'name' => 'Human',
            'description' => 'A humanoid creature in the Star Wars universe.',
            'fields' => [
                'id' => [
                    'type' => Type::nonNull(Type::string()),
                    'description' => 'The id of the human.',
                ],
                'name' => [
                    'type' => Type::string(),
                    'description' => 'The name of the human.',
                ],
            ],
        ]);
    }

    /**
     * Generate query type.
     *
     * @param  ObjectType $type
     * @return ObjectType
     */
    protected function getQueryType($type)
    {
        return new ObjectType([
            'name' => 'HumanQuery',
            'fields' => [
                'human' => [
                    'type' => $type,
                    'args' => []
                ]
            ]
        ]);
    }

    /**
     * Generate mutation type.
     *
     * @param  ObjectType $type
     * @return ObjectType
     */
    protected function getMutationType($type)
    {
        return new ObjectType([
            'name' => 'FooMutation',
            'type' => $type,
            'args' => [
                'foo' => [
                    'name' => 'foo',
                    'type' => Type::string(),
                ]
            ]
        ]);
    }
}
