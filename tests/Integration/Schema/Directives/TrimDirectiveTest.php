<?php

namespace Tests\Integration\Schema\Directives;

use Tests\DBTestCase;

class TrimDirectiveTest extends DBTestCase
{
    public function testTrimsInput(): void
    {
        $this->schema .= '
        type Company {
            id: ID!
            name: String!
        }
        
        type Mutation {
            createCompany(name: String @trim): Company @create
        }
        ';

        $this->graphQL('
        mutation {
            createCompany(name: "    foo     ") {
                id
                name
            }
        }
        ')->assertJson([
            'data' => [
                'createCompany' => [
                    'id' => '1',
                    'name' => 'foo',
                ],
            ],
        ]);
    }
}
