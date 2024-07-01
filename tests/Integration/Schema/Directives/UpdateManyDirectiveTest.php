<?php declare(strict_types=1);

namespace Tests\Integration\Schema\Directives;

use Illuminate\Database\Eloquent\ModelNotFoundException;
use Nuwave\Lighthouse\Exceptions\DefinitionException;
use Nuwave\Lighthouse\Schema\Directives\ManyModelMutationDirective;
use Tests\DBTestCase;
use Tests\Utils\Models\Company;

final class UpdateManyDirectiveTest extends DBTestCase
{
    public function testUpdateFromFieldArguments(): void
    {
        $company1 = factory(Company::class)->create();
        assert($company1 instanceof Company);
        $company1->name = 'foo1';
        $company1->save();

        $company2 = factory(Company::class)->create();
        assert($company2 instanceof Company);
        $company2->name = 'unchanged';
        $company2->save();

        $this->schema .= /** @lang GraphQL */ '
        type Company {
            id: ID!
            name: String!
        }

        input UpdateCompanyInput {
            id: ID!
            name: String
        }

        type Mutation {
            updateCompanies(inputs: [UpdateCompanyInput!]!): [Company!]! @updateMany
        }
        ';

        $this->graphQL(/** @lang GraphQL */ '
        mutation {
            updateCompanies(inputs: [
                {
                    id: 1
                    name: "foo2"
                }
                {
                    id: 2
                    name: "unchanged"
                }
            ]) {
                id
                name
            }
        }
        ')->assertJson([
            'data' => [
                'updateCompanies' => [
                    [
                        'id' => '1',
                        'name' => 'foo2',
                    ],
                    [
                        'id' => '2',
                        'name' => 'unchanged',
                    ],
                ],
            ],
        ]);
    }

    public function testEmptyInputs(): void
    {
        $this->schema .= /** @lang GraphQL */ '
        type Company {
            id: ID!
            name: String!
        }

        input UpdateCompanyInput {
            id: ID!
            name: String
        }

        type Mutation {
            updateCompanies(inputs: [UpdateCompanyInput!]!): [Company!]! @updateMany
        }
        ';

        $this->graphQL(/** @lang GraphQL */ '
        mutation {
            updateCompanies(inputs: []) {
                id
                name
            }
        }
        ')->assertJson([
            'data' => [
                'updateCompanies' => [],
            ],
        ]);
    }

    public function testRollsBackAllChangesOnPartialFailure(): void
    {
        $name = 'foo1';

        $company1 = factory(Company::class)->create();
        assert($company1 instanceof Company);
        $company1->name = $name;
        $company1->save();

        $this->schema .= /** @lang GraphQL */ '
        type Company {
            id: ID!
            name: String!
        }

        input UpdateCompanyInput {
            id: ID!
            name: String
        }

        type Mutation {
            updateCompanies(inputs: [UpdateCompanyInput]!): [Company!]! @updateMany
        }
        ';

        $exception = null;
        try {
            $this->graphQL(/** @lang GraphQL */ '
            mutation {
                updateCompanies(inputs: [
                    {
                        id: 1
                        name: "foo2"
                    }
                    {
                        id: 2
                        name: "does not exist"
                    }
                ]) {
                    id
                    name
                }
            }
            ');
        } catch (ModelNotFoundException $modelNotFoundException) {
            $exception = $modelNotFoundException;
        }

        $this->assertNotNull($exception);

        $this->assertSame($name, $company1->refresh()->name);
    }

    /** TODO consider either throwing or ensuring the latest results for each entry */
    public function testSameIDMultipleTimes(): void
    {
        $company1 = factory(Company::class)->create();
        assert($company1 instanceof Company);
        $company1->name = 'foo1';
        $company1->save();

        $this->schema .= /** @lang GraphQL */ '
        type Company {
            id: ID!
            name: String!
        }

        input UpdateCompanyInput {
            id: ID!
            name: String
        }

        type Mutation {
            updateCompanies(inputs: [UpdateCompanyInput]!): [Company!]! @updateMany
        }
        ';

        $this->graphQL(/** @lang GraphQL */ '
        mutation {
            updateCompanies(inputs: [
                {
                    id: 1
                    name: "foo2"
                }
                {
                    id: 1
                    name: "foo3"
                }
            ]) {
                id
                name
            }
        }
        ')->assertJson([
            'data' => [
                'updateCompanies' => [
                    [
                        'id' => '1',
                        'name' => 'foo2',
                    ],
                    [
                        'id' => '1',
                        'name' => 'foo3',
                    ],
                ],
            ],
        ]);
    }

    public function testMissingArgument(): void
    {
        $this->schema .= /** @lang GraphQL */ '
        type Company {
            id: ID!
            name: String!
        }

        input UpdateCompanyInput {
            id: ID!
            name: String
        }

        type Mutation {
            updateCompanies(inputs: [UpdateCompanyInput!]): [Company!]! @updateMany
        }
        ';

        $this->expectExceptionObject(new DefinitionException(
            ManyModelMutationDirective::NOT_EXACTLY_ONE_ARGUMENT,
        ));
        $this->graphQL(/** @lang GraphQL */ '
        mutation {
            updateCompanies {
                id
                name
            }
        }
        ');
    }

    public function testMultipleArguments(): void
    {
        $this->schema .= /** @lang GraphQL */ '
        type Company {
            id: ID!
            name: String!
        }

        type Mutation {
            updateCompanies(foo: String, bar: String): [Company!]! @updateMany
        }
        ';

        $this->expectExceptionObject(new DefinitionException(
            ManyModelMutationDirective::NOT_EXACTLY_ONE_ARGUMENT,
        ));
        $this->graphQL(/** @lang GraphQL */ '
        mutation {
            updateCompanies(foo: "asf", bar: null) {
                id
                name
            }
        }
        ');
    }

    public function testNotList(): void
    {
        $this->schema .= /** @lang GraphQL */ '
        type Company {
            id: ID!
            name: String!
        }

        input UpdateCompanyInput {
            id: ID!
            name: String
        }

        type Mutation {
            updateCompanies(inputs: UpdateCompanyInput!): [Company!]! @updateMany
        }
        ';

        $this->expectExceptionObject(new DefinitionException(
            ManyModelMutationDirective::ARGUMENT_NOT_LIST,
        ));
        $this->graphQL(/** @lang GraphQL */ '
        mutation {
            updateCompanies(inputs: {
                id: 1
                name: "foo"
            }) {
                id
                name
            }
        }
        ');
    }

    public function testNotInputObjects(): void
    {
        $this->schema .= /** @lang GraphQL */ '
        type Company {
            id: ID!
            name: String!
        }

        type Mutation {
            updateCompanies(inputs: [String!]!): [Company!]! @updateMany
        }
        ';

        $this->expectExceptionObject(new DefinitionException(
            ManyModelMutationDirective::LIST_ITEM_NOT_INPUT_OBJECT,
        ));
        $this->graphQL(/** @lang GraphQL */ '
        mutation {
            updateCompanies(inputs: ["foo"]) {
                id
                name
            }
        }
        ');
    }
}
