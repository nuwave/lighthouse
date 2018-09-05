<?php

namespace Tests\Unit\Schema\Directives\Fields;

use Tests\TestCase;

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

    protected function getConnectionQueryField($type)
    {
        return $this->buildSchemaFromString("
        type Users {
            name: String
        }
        
        type Query {
            users: [User!]! @paginate(type: \"$type\" model: \"User\")
        }
        ")->getQueryType()->getField('users');
    }
}
