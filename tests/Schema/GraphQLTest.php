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
        $graphql = app('graphql');

        $graphql->addQuery($queryType, 'humanQuery');

        $schema = app('graphql')->schema();
        $schemaQuery = $schema->getQueryType();

        $this->assertTrue($this->hasType($schemaQuery, 'HumanQuery'));
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
     * @param  mixed $type
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
}
