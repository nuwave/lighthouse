<?php

namespace Tests\Unit\Schema\Directives\Fields;

use Tests\TestCase;
use GraphQL\Type\Definition\FieldDefinition;

class PaginateDirectiveTest extends TestCase
{
    /**
     * @test
     */
    public function itCanAliasRelayToConnection()
    {
        $connection = $this->getConnectionQueryField('connection');
        $relay = $this->getConnectionQueryField('relay');

        $this->assertEquals($connection, $relay);
    }

    protected function getConnectionQueryField(string $type): FieldDefinition
    {
        return $this
            ->buildSchemaFromString("
            type User {
                name: String
            }
            
            type Query {
                users: [User!]! @paginate(type: \"$type\")
            }
            ")
            ->getQueryType()
            ->getField('users');
    }
}
