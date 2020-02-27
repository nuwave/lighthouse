<?php

namespace Tests\Integration\Validation;

use Tests\DBTestCase;
use Tests\Utils\Models\Company;
use Tests\Utils\Models\User;

class RulesDirectiveValidatorClassTest extends DBTestCase
{
    protected $schema = /** @lang GraphQL */ '
    type Company {
      id: ID!
      name: String!
    }

    type User {
      id: ID!
      name: String!
      email: String!
      company: Company! @belongsTo
    }

    input UpdateUserInput @rules(validator: "Tests\\\\Utils\\\\Validators\\\\UpdateUserInputValidator") {
      id: ID!
      name: String
      email: String
      password: String
      company: ManageCompanyRelation
    }

    input ManageCompanyRelation {
      update: UpdateCompanyInput
    }

    input CreateUserInput @rules {
      name: String!
      email: String!
      password: String!
    }

    input UpdateCompanyInput @rules(validator: "Tests\\\\Utils\\\\Validators\\\\UpdateCompanyInputValidator") {
      id: ID!
      name: String!
    }

    type Mutation {
      updateUser(input: UpdateUserInput! @spread): User @update
      createUser(input: CreateUserInput! @spread): User @create
    }

    type Query {
      me: User @auth
    }
    ';

    public function testInputTypeValidator()
    {
        $mutation = /** @lang GraphQL */ '
        mutation ($input: CreateUserInput!) {
            createUser(input: $input){
                email
            }
        }
        ';
        $successful = $this->graphQL($mutation, [
            'input' => [
                'name' => 'Username',
                'email' => 'user@company.test',
                'password' => 'supersecret',
            ],
        ]
        );

        $successful->assertJson([
            'data' => [
                'createUser' => [
                    'email' => 'user@company.test',
                ],
            ],
        ]);

        $fails = $this->graphQL($mutation, [
            'input' => [
                'name' => 'n',
                'email' => 'string',
                'password' => 's',
            ],
        ]);

        $this->assertValidationError($fails, 'input.name', 'Name validation message.');
        $this->assertValidationError($fails, 'input.email', 'The input.email must be a valid email address.');
        $this->assertValidationError($fails, 'input.password', 'The input.password must be at least 11 characters.');
    }

    public function testNestedInputTypeValidator()
    {
        $company = factory(Company::class)->create(['name' => 'The Company']);
        factory(Company::class)->create(['name' => 'The Second Company']);
        $user = factory(User::class)->create(['company_id' => $company->id]);

        $mutation = /** @lang GraphQL */
            '
        mutation ($input: UpdateUserInput!){
          updateUser(input: $input){
            company {
              name
            }
          }
        }
        ';
        $successful = $this->graphQL($mutation, [
            'input' => [
                'id' => $user->id,
                'company' => [
                    'update' => [
                        'id' => $company->id,
                        'name' => 'The Company',
                    ],
                ],
            ],
        ]);

        $successful->assertJson([
            'data' => [
                'updateUser' => [
                    'company' => [
                        'name' => 'The Company',
                    ],
                ],
            ],
        ]);

        $fail = $this->graphQL($mutation, [
            'input' => [
                'id' => $user->id,
                'company' => [
                    'update' => [
                        'id' => $company->id,
                        'name' => 'The Second Company',
                    ],
                ],
            ],
        ]);

        $this->assertValidationError($fail, 'input.company.update.name', 'The input.company.update.name has already been taken.');
    }
}
