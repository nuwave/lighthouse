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
        $this->assertInstanceOf(Company::class, $company1);
        $company1->name = 'foo1';
        $company1->save();

        $company2 = factory(Company::class)->create();
        $this->assertInstanceOf(Company::class, $company2);
        $company2->name = 'unchanged';
        $company2->save();

        $this->schema .= /** @lang GraphQL */ <<<'GRAPHQL'
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
        GRAPHQL;

        $this->graphQL(/** @lang GraphQL */ <<<'GRAPHQL'
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
        GRAPHQL)->assertJson([
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
        $this->schema .= /** @lang GraphQL */ <<<'GRAPHQL'
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
        GRAPHQL;

        $this->graphQL(/** @lang GraphQL */ <<<'GRAPHQL'
        mutation {
            updateCompanies(inputs: []) {
                id
                name
            }
        }
        GRAPHQL)->assertJson([
            'data' => [
                'updateCompanies' => [],
            ],
        ]);
    }

    public function testRollsBackAllChangesOnPartialFailure(): void
    {
        $name = 'foo1';

        $company1 = factory(Company::class)->create();
        $this->assertInstanceOf(Company::class, $company1);
        $company1->name = $name;
        $company1->save();

        $this->schema .= /** @lang GraphQL */ <<<'GRAPHQL'
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
        GRAPHQL;

        $exception = null;
        try {
            $this->graphQL(/** @lang GraphQL */ <<<'GRAPHQL'
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
            GRAPHQL);
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
        $this->assertInstanceOf(Company::class, $company1);
        $company1->name = 'foo1';
        $company1->save();

        $this->schema .= /** @lang GraphQL */ <<<'GRAPHQL'
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
        GRAPHQL;

        $this->graphQL(/** @lang GraphQL */ <<<'GRAPHQL'
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
        GRAPHQL)->assertJson([
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
        $this->schema .= /** @lang GraphQL */ <<<'GRAPHQL'
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
        GRAPHQL;

        $this->expectExceptionObject(new DefinitionException(
            ManyModelMutationDirective::NOT_EXACTLY_ONE_ARGUMENT,
        ));
        $this->graphQL(/** @lang GraphQL */ <<<'GRAPHQL'
        mutation {
            updateCompanies {
                id
                name
            }
        }
        GRAPHQL);
    }

    public function testMultipleArguments(): void
    {
        $this->schema .= /** @lang GraphQL */ <<<'GRAPHQL'
        type Company {
            id: ID!
            name: String!
        }

        type Mutation {
            updateCompanies(foo: String, bar: String): [Company!]! @updateMany
        }
        GRAPHQL;

        $this->expectExceptionObject(new DefinitionException(
            ManyModelMutationDirective::NOT_EXACTLY_ONE_ARGUMENT,
        ));
        $this->graphQL(/** @lang GraphQL */ <<<'GRAPHQL'
        mutation {
            updateCompanies(foo: "asf", bar: null) {
                id
                name
            }
        }
        GRAPHQL);
    }

    public function testNotList(): void
    {
        $this->schema .= /** @lang GraphQL */ <<<'GRAPHQL'
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
        GRAPHQL;

        $this->expectExceptionObject(new DefinitionException(
            ManyModelMutationDirective::ARGUMENT_NOT_LIST,
        ));
        $this->graphQL(/** @lang GraphQL */ <<<'GRAPHQL'
        mutation {
            updateCompanies(inputs: {
                id: 1
                name: "foo"
            }) {
                id
                name
            }
        }
        GRAPHQL);
    }

    public function testNotInputObjects(): void
    {
        $this->schema .= /** @lang GraphQL */ <<<'GRAPHQL'
        type Company {
            id: ID!
            name: String!
        }

        type Mutation {
            updateCompanies(inputs: [String!]!): [Company!]! @updateMany
        }
        GRAPHQL;

        $this->expectExceptionObject(new DefinitionException(
            ManyModelMutationDirective::LIST_ITEM_NOT_INPUT_OBJECT,
        ));
        $this->graphQL(/** @lang GraphQL */ <<<'GRAPHQL'
        mutation {
            updateCompanies(inputs: ["foo"]) {
                id
                name
            }
        }
        GRAPHQL);
    }
}
