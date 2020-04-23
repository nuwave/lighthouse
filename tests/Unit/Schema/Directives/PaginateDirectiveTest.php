<?php

namespace Tests\Unit\Schema\Directives;

use GraphQL\Type\Definition\FieldArgument;
use GraphQL\Type\Definition\FieldDefinition;
use Nuwave\Lighthouse\Exceptions\DefinitionException;
use Nuwave\Lighthouse\Pagination\PaginationArgs;
use Tests\TestCase;

class PaginateDirectiveTest extends TestCase
{
    public function testCanAliasRelayToConnection(): void
    {
        $connection = $this->getConnectionQueryField('connection');
        $relay = $this->getConnectionQueryField('relay');

        $this->assertSame($connection, $relay);
    }

    protected function getConnectionQueryField(string $type): FieldDefinition
    {
        return $this
            ->buildSchema(/** @lang GraphQL */ "
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

    public function testOnlyRegistersOneTypeForMultiplePaginators(): void
    {
        $schema = $this->buildSchema(/** @lang GraphQL */ '
        type User {
            name: String
            users: [User!]! @paginate
            users2: [User!]! @paginate(type: "relay")
            users3: [User!]! @paginate(type: "connection")
        }

        type Query {
            users: [User!]! @paginate
            users2: [User!]! @paginate(type: "relay")
            users3: [User!]! @paginate(type: "connection")
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
        config(['lighthouse.paginate_max_count' => 5]);

        $queryType = $this
            ->buildSchema(/** @lang GraphQL */ '
            type Query {
                defaultPaginated: [User!]! @paginate
                defaultRelay: [User!]! @paginate(type: "relay")
                customPaginated:  [User!]! @paginate(maxCount: 10)
                customRelay:  [User!]! @paginate(maxCount: 10, type: "relay")
            }

            type User {
                id: ID!
            }
            ')
            ->getQueryType();

        $this->assertSame(
            'Limits number of fetched elements. Maximum allowed value: 5.',
            $queryType->getField('defaultPaginated')->getArg(config('lighthouse.pagination_amount_argument'))->description
        );

        $this->assertSame(
            'Limits number of fetched elements. Maximum allowed value: 5.',
            $queryType->getField('defaultRelay')->getArg('first')->description
        );

        $this->assertSame(
            'Limits number of fetched elements. Maximum allowed value: 10.',
            $queryType->getField('customPaginated')->getArg(config('lighthouse.pagination_amount_argument'))->description
        );

        $this->assertSame(
            'Limits number of fetched elements. Maximum allowed value: 10.',
            $queryType->getField('customRelay')->getArg('first')->description
        );
    }

    public function testCanChangePaginationAmountArgument(): void
    {
        config(['lighthouse.pagination_amount_argument' => 'first']);

        $queryType = $this
            ->buildSchema(/** @lang GraphQL */ '
            type Query {
                defaultPaginated: [User!]! @paginate
            }

            type User {
                id: ID!
            }
            ')
            ->getQueryType();

        $this->assertInstanceOf(
            FieldArgument::class,
            $queryType->getField('defaultPaginated')->getArg('first')
        );
    }

    public function testIsLimitedByMaxCountFromDirective(): void
    {
        config(['lighthouse.paginate_max_count' => 5]);

        $this->schema = /** @lang GraphQL */
            '
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
            $result->jsonGet('errors.0.message')
        );
    }

    public function testIsLimitedToMaxCountFromConfig(): void
    {
        config(['lighthouse.paginate_max_count' => 5]);

        $this->schema = /** @lang GraphQL */ '
        type User {
            id: ID!
            name: String!
        }

        type Query {
            users1: [User!]! @paginate
            users2: [User!]! @paginate(type: "relay")
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
            $resultFromDefaultPagination->jsonGet('errors.0.message')
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
            $resultFromRelayPagination->jsonGet('errors.0.message')
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
            $result->jsonGet('errors.0.message')
        );
    }

    public function testDoesNotRequireModelWhenUsingBuilder(): void
    {
        $validationErrors = $this
            ->buildSchema(/** @lang GraphQL */ '
            type Query {
                users: [NotAnActualModelName!] @paginate(builder: "'.$this->qualifyTestResolver('testDoesNotRequireModelWhenUsingBuilder').'")
            }

            type NotAnActualModelName {
                id: ID!
            }
            ')
            ->validate();

        $this->assertCount(0, $validationErrors);
    }

    public function testThrowsIfBuilderIsNotPresent(): void
    {
        $this->expectException(DefinitionException::class);
        $this->expectExceptionMessageRegExp('/NonexistingClass/');
        $this->buildSchema(/** @lang GraphQL */ '
        type Query {
            users: [Query!] @paginate(builder: "NonexistingClass@notFound")
        }
        ');
    }
}
