<?php

namespace Tests\Integration\Schema\Directives;

use Tests\DBTestCase;

class TrimDirectiveTest extends DBTestCase
{
    /**
     * @test
     */
    public function itTrimsInput(): void
    {
        $this->schema = '
        type Company {
            id: ID!
            name: String!
        }
        
        type Mutation {
            createCompany(name: String @trim): Company @create
        }
        '.$this->placeholderQuery();

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
