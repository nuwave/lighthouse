<?php

namespace Tests\Integration\Schema\Directives;

use Illuminate\Validation\Rule;
use Nuwave\Lighthouse\Execution\InputValidator;
use Tests\DBTestCase;
use Tests\Utils\Models\Company;
use Tests\Utils\Models\User;

/**
 * Class ValidatorDirectiveTest.
 */
class ValidatorDirectiveTest extends DBTestCase
{
    protected $schema = /** @lang GraphQL */
        '
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

        input UpdateUserInput @validate(validator: "Tests\\\\Utils\\\\Validators\\\\UpdateUserInputValidator") {
          id: ID!
          name: String
          email: String
          password: String
          company: ManageCompanyRelation
        }

        input ManageCompanyRelation {
          update: UpdateCompanyInput
        }

        input CreateUserInput @validate {
          name: String!
          email: String!
          password: String!
        }

        input UpdateCompanyInput @validate(validator: "Tests\\\\Utils\\\\Validators\\\\UpdateCompanyInputValidator") {
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
        $response = $this->graphQL(
/** @lang GraphQL */ '
                mutation ($input: CreateUserInput!){
                  createUser(input: $input){
                   email
                }
              }
                ',
            [
                'input' => [
                    'name'     => 'Username',
                    'email'    => 'user@company.test',
                    'password' => 'supersecret',
                ],
            ]
        );

        $response->assertJson([
            'data' => [
                'createUser' => [
                    'email' => 'user@company.test',
                ],
            ],
        ]);
    }

    public function testNestedInputTypeValidator()
    {
        $company = factory(Company::class)->create(['name' => 'The Company']);
        $user = factory(User::class)->create(['company_id' => $company->id]);

        $response = $this->graphQL(/** @lang GraphQL */ '
        mutation ($input: UpdateUserInput!){
          updateUser(input: $input){
            company {
              name
            }
          }
        }
        ', [
            'input' => [
                'id'      => $user->id,
                'company' => [
                    'update' => [
                        'id'   => $company->id,
                        'name' => 'The Company',
                    ],
                ],
            ],
        ]);

        $response->assertJson([
            'data' => [
                'updateUser' => [
                    'company' => [
                        'name' => 'The Company',
                    ],
                ],
            ],
        ]);
    }

    public function testValidationMessages()
    {

        $response = $this->graphQL(
/** @lang GraphQL */ '
                mutation ($input: CreateUserInput!){
                  createUser(input: $input){
                   email
                }
              }
                ',
            [
                'input' => [
                    'name'     => 'short',
                    'email'    => 'user@company.test',
                    'password' => 'supersecret',
                ],
            ]
        );

        $this->assertValidationError($response, 'input.name', 'Name validation message.');
    }
}
