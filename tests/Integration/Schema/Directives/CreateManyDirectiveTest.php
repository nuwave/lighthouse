<?php declare(strict_types=1);

namespace Tests\Integration\Schema\Directives;

use Tests\DBTestCase;

final class CreateManyDirectiveTest extends DBTestCase
{
    public function testCreateFromFieldArguments(): void
    {
        $this->schema .= /** @lang GraphQL */ '
        type Company {
            id: ID!
            name: String!
        }

        input CreateCompanyInput {
            name: String!
        }

        type Mutation {
            createCompanies(inputs: [CreateCompanyInput!]!): [Company!]! @createMany
        }
        ';

        $this->graphQL(/** @lang GraphQL */ '
        mutation {
            createCompanies(inputs: [
                {
                    name: "foo"
                }
                {
                    name: "bar"
                }
            ]) {
                id
                name
            }
        }
        ')->assertJson([
            'data' => [
                'createCompanies' => [
                    [
                        'id' => '1',
                        'name' => 'foo',
                    ],
                    [
                        'id' => '2',
                        'name' => 'bar',
                    ],
                ],
            ],
        ]);
    }
}
