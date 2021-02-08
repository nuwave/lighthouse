<?php

namespace Tests\Unit\Pagination;

use GraphQL\Type\Definition\FieldArgument;
use GraphQL\Type\Definition\FieldDefinition;
use Nuwave\Lighthouse\Exceptions\DefinitionException;
use Nuwave\Lighthouse\Pagination\PaginationArgs;
use Nuwave\Lighthouse\Pagination\PaginationType;
use Tests\TestCase;

class PaginateDirectiveTest extends TestCase
{
    public function testCanAliasRelayToConnection(): void
    {
        $connection = $this->getConnectionQueryField(PaginationType::CONNECTION);
        $relay = $this->getConnectionQueryField('relay');

        $this->assertSame($connection, $relay);
    }

    protected function getConnectionQueryField(string $type): FieldDefinition
    {
        $schema = $this->buildSchema(/** @lang GraphQL */ "
        type User {
            name: String
        }

        type Query {
            users: [User!]! @paginate(type: {$type})
        }
        ");

        /** @var \GraphQL\Type\Definition\ObjectType $queryType */
        $queryType = $schema->getQueryType();

        return $queryType->getField('users');
    }

    public function testOnlyRegistersOneTypeForMultiplePaginators(): void
    {
        $schema = $this->buildSchema(/** @lang GraphQL */ '
        type User {
            name: String
            users: [User!]! @paginate
            users2: [User!]! @paginate(type: CONNECTION)
            users3: [User!]! @paginate(type: "relay")
        }

        type Query {
            users: [User!]! @paginate
            users2: [User!]! @paginate(type: CONNECTION)
            users3: [User!]! @paginate(type: "relay")
        }
        ');
        $typeMap = $schema->getTypeMap();

        $this->assertArrayHasKey(
            'UserPaginator',
            $typeMap
        );

        $this->assertArrayHasKey(
            'UserConnection',
            $typeMap
        );
    }

    public function testRegistersPaginatorFromTypeExtensionField(): void
    {
        $schema = $this->buildSchemaWithPlaceholderQuery(/** @lang GraphQL */ '
        type User {
            id: ID!
            name: String!
        }

        extend type Query {
            users: [User!]! @paginate
        }
        ');
        $typeMap = $schema->getTypeMap();

        $this->assertArrayHasKey(
            'UserPaginator',
            $typeMap
        );

        // See https://github.com/nuwave/lighthouse/issues/387
        $this->assertArrayNotHasKey(
            'UserPaginatorPaginator',
            $typeMap
        );
    }

    public function testHasMaxCountInGeneratedCountDescription(): void
    {
        config(['lighthouse.pagination.max_count' => 5]);

        /** @var \GraphQL\Type\Definition\ObjectType $queryType */
        $queryType = $this
            ->buildSchema(/** @lang GraphQL */ '
            type Query {
                defaultPaginated: [User!]! @paginate
                defaultRelay: [User!]! @paginate(type: CONNECTION)
                customPaginated:  [User!]! @paginate(maxCount: 10)
                customRelay:  [User!]! @paginate(maxCount: 10, type: CONNECTION)
            }

            type User {
                id: ID!
            }
            ')
            ->getQueryType();

        $defaultPaginatedAmountArg = $queryType
            ->getField('defaultPaginated')
            ->getArg('first');

        $this->assertInstanceOf(FieldArgument::class, $defaultPaginatedAmountArg);
        /** @var \GraphQL\Type\Definition\FieldArgument $defaultPaginatedAmountArg */
        $this->assertSame(
            'Limits number of fetched elements. Maximum allowed value: 5.',
            $defaultPaginatedAmountArg->description
        );

        $defaultRelayFirstArg = $queryType
            ->getField('defaultRelay')
            ->getArg('first');

        $this->assertInstanceOf(FieldArgument::class, $defaultRelayFirstArg);
        /** @var \GraphQL\Type\Definition\FieldArgument $defaultRelayFirstArg */
        $this->assertSame(
            'Limits number of fetched elements. Maximum allowed value: 5.',
            $defaultRelayFirstArg->description
        );

        $customPaginatedAmountArg = $queryType
            ->getField('customPaginated')
            ->getArg('first');

        $this->assertInstanceOf(FieldArgument::class, $customPaginatedAmountArg);
        /** @var \GraphQL\Type\Definition\FieldArgument $customPaginatedAmountArg */
        $this->assertSame(
            'Limits number of fetched elements. Maximum allowed value: 10.',
            $customPaginatedAmountArg->description
        );

        $customRelayFirstArg = $queryType
            ->getField('customRelay')
            ->getArg('first');

        $this->assertInstanceOf(FieldArgument::class, $customRelayFirstArg);
        /** @var \GraphQL\Type\Definition\FieldArgument $customRelayFirstArg */
        $this->assertSame(
            'Limits number of fetched elements. Maximum allowed value: 10.',
            $customRelayFirstArg->description
        );
    }

    public function testIsLimitedByMaxCountFromDirective(): void
    {
        config(['lighthouse.pagination.max_count' => 5]);

        $this->schema = /** @lang GraphQL */'
        type User {
            id: ID!
            name: String!
        }

        type Query {
            users1: [User!]! @paginate(maxCount: 6)
            users2: [User!]! @paginate(maxCount: 10)
        }
        ';

        $result = $this->graphQL(/** @lang GraphQL */ '
        {
            users1(first: 10) {
                data {
                    id
                    name
                }
            }
        }
        ');

        $this->assertSame(
            PaginationArgs::requestedTooManyItems(6, 10),
            $result->json('errors.0.message')
        );
    }

    public function testIsLimitedToMaxCountFromConfig(): void
    {
        config(['lighthouse.pagination.max_count' => 5]);

        $this->schema = /** @lang GraphQL */ '
        type User {
            id: ID!
            name: String!
        }

        type Query {
            users1: [User!]! @paginate
            users2: [User!]! @paginate(type: CONNECTION)
        }
        ';

        $resultFromDefaultPagination = $this->graphQL(/** @lang GraphQL */ '
        {
            users1(first: 10) {
                data {
                    id
                    name
                }
            }
        }
        ');

        $this->assertSame(
            PaginationArgs::requestedTooManyItems(5, 10),
            $resultFromDefaultPagination->json('errors.0.message')
        );

        $resultFromRelayPagination = $this->graphQL(/** @lang GraphQL */ '
        {
            users2(first: 10) {
                edges {
                    node {
                        id
                        name
                    }
                }
            }
        }
        ');

        $this->assertSame(
            PaginationArgs::requestedTooManyItems(5, 10),
            $resultFromRelayPagination->json('errors.0.message')
        );
    }

    public function testThrowsWhenPaginationWithCountZeroIsRequested(): void
    {
        $this->schema = /** @lang GraphQL */ '
        type User {
            id: ID!
            name: String!
        }

        type Query {
            users: [User!] @paginate
        }
        ';

        $result = $this->graphQL(/** @lang GraphQL */ '
        {
            users(first: 0) {
                data {
                    id
                }
            }
        }
        ')
        ->assertJson([
            'data' => [
                'users' => null,
            ],
        ]);

        $this->assertSame(
            PaginationArgs::requestedZeroOrLessItems(0),
            $result->json('errors.0.message')
        );
    }

    public function testDoesNotRequireModelWhenUsingBuilder(): void
    {
        $validationErrors = $this
            ->buildSchema(/** @lang GraphQL */ "
            type Query {
                users: [NotAnActualModelName!] @paginate(builder: \"{$this->qualifyTestResolver('testDoesNotRequireModelWhenUsingBuilder')}\")
            }

            type NotAnActualModelName {
                id: ID!
            }
            ")
            ->validate();

        $this->assertCount(0, $validationErrors);
    }

    public function testThrowsIfBuilderIsNotPresent(): void
    {
        $this->expectException(DefinitionException::class);
        $this->expectExceptionMessage('No class `NonexistingClass` was found for directive `@paginate`');

        $this->buildSchema(/** @lang GraphQL */ '
        type Query {
            users: [Query!] @paginate(builder: "NonexistingClass@notFound")
        }
        ');
    }
}
