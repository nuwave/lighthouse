<?php

namespace Tests\Unit\Pagination;

use GraphQL\Type\Definition\FieldArgument;
use GraphQL\Type\Definition\FieldDefinition;
use GraphQL\Type\Definition\ObjectType;
use GraphQL\Utils\SchemaPrinter;
use Nuwave\Lighthouse\Exceptions\DefinitionException;
use Nuwave\Lighthouse\Pagination\PaginationArgs;
use Nuwave\Lighthouse\Pagination\PaginationType;
use Tests\TestCase;

class PaginateDirectiveTest extends TestCase
{
    public function testIncludesPaginationInfoObjectsInSchema(): void
    {
        $schema = $this->buildSchemaWithPlaceholderQuery();
        $schemaString = SchemaPrinter::doPrint($schema);

        $this->assertStringContainsString(/** @lang GraphQL */ <<<'GRAPHQL'
"""Information about pagination using a Relay style cursor connection."""
type PageInfo {
  """When paginating forwards, are there more items?"""
  hasNextPage: Boolean!

  """When paginating backwards, are there more items?"""
  hasPreviousPage: Boolean!

  """The cursor to continue paginating backwards."""
  startCursor: String

  """The cursor to continue paginating forwards."""
  endCursor: String

  """Total number of nodes in the paginated connection."""
  total: Int!

  """Number of nodes in the current page."""
  count: Int!

  """Index of the current page."""
  currentPage: Int!

  """Index of the last available page."""
  lastPage: Int!
}
GRAPHQL
            ,
            $schemaString
        );

        $this->assertStringContainsString(/** @lang GraphQL */ <<<'GRAPHQL'
"""Information about pagination using a fully featured paginator."""
type PaginatorInfo {
  """Number of items in the current page."""
  count: Int!

  """Index of the current page."""
  currentPage: Int!

  """Index of the first item in the current page."""
  firstItem: Int

  """Are there more pages after this one?"""
  hasMorePages: Boolean!

  """Index of the last item in the current page."""
  lastItem: Int

  """Index of the last available page."""
  lastPage: Int!

  """Number of items per page."""
  perPage: Int!

  """Number of total available items."""
  total: Int!
}
GRAPHQL
            ,
            $schemaString
        );

        $this->assertStringContainsString(/** @lang GraphQL */ <<<'GRAPHQL'
"""Information about pagination using a simple paginator."""
type SimplePaginatorInfo {
  """Number of items in the current page."""
  count: Int!

  """Index of the current page."""
  currentPage: Int!

  """Index of the first item in the current page."""
  firstItem: Int

  """Index of the last item in the current page."""
  lastItem: Int

  """Number of items per page."""
  perPage: Int!
}
GRAPHQL
            ,
            $schemaString
        );
    }

    /**
     * @dataProvider nonNullPaginationResults
     */
    public function testManipulatesPaginator(bool $nonNullPaginationResults): void
    {
        config(['lighthouse.non_null_pagination_results' => $nonNullPaginationResults]);

        $schema = $this->buildSchema(/** @lang GraphQL */ '
        type User {
            id: ID!
        }

        type Query {
            users: [User!]! @paginate
        }
        ');
        $schemaString = SchemaPrinter::doPrint($schema);

        $nonNull = $nonNullPaginationResults
            ? '!'
            : '';
        $this->assertStringContainsString(/** @lang GraphQL */ <<<GRAPHQL
type Query {
  users(
    """Limits number of fetched items."""
    first: Int!

    """The offset from which items are returned."""
    page: Int
  ): UserPaginator{$nonNull}
}
GRAPHQL
            ,
            $schemaString
        );

        $this->assertStringContainsString(/** @lang GraphQL */ <<<'GRAPHQL'
"""A paginated list of User items."""
type UserPaginator {
  """Pagination information about the list of items."""
  paginatorInfo: PaginatorInfo!

  """A list of User items."""
  data: [User!]!
}
GRAPHQL
            ,
            $schemaString
        );
    }

    /**
     * @dataProvider nonNullPaginationResults
     */
    public function testManipulatesSimplePaginator(bool $nonNullPaginationResults): void
    {
        config(['lighthouse.non_null_pagination_results' => $nonNullPaginationResults]);

        $schema = $this->buildSchema(/** @lang GraphQL */ '
        type User {
            id: ID!
        }

        type Query {
            users: [User!]! @paginate(type: SIMPLE)
        }
        ');
        $schemaString = SchemaPrinter::doPrint($schema);

        $nonNull = $nonNullPaginationResults
            ? '!'
            : '';
        $this->assertStringContainsString(/** @lang GraphQL */ <<<GRAPHQL
type Query {
  users(
    """Limits number of fetched items."""
    first: Int!

    """The offset from which items are returned."""
    page: Int
  ): UserSimplePaginator{$nonNull}
}
GRAPHQL
            ,
            $schemaString
        );

        $this->assertStringContainsString(/** @lang GraphQL */ <<<'GRAPHQL'
"""A paginated list of User items."""
type UserSimplePaginator {
  """Pagination information about the list of items."""
  paginatorInfo: SimplePaginatorInfo!

  """A list of User items."""
  data: [User!]!
}
GRAPHQL
            ,
            $schemaString
        );
    }

    /**
     * @dataProvider nonNullPaginationResults
     */
    public function testManipulatesConnection(bool $nonNullPaginationResults): void
    {
        config(['lighthouse.non_null_pagination_results' => $nonNullPaginationResults]);

        $schema = $this->buildSchema(/** @lang GraphQL */ '
        type User {
            id: ID!
        }

        type Query {
            users: [User!]! @paginate(type: CONNECTION)
        }
        ');
        $schemaString = SchemaPrinter::doPrint($schema);

        $nonNull = $nonNullPaginationResults
            ? '!'
            : '';
        $this->assertStringContainsString(/** @lang GraphQL */ <<<GRAPHQL
type Query {
  users(
    """Limits number of fetched items."""
    first: Int!

    """A cursor after which elements are returned."""
    after: String
  ): UserConnection{$nonNull}
}
GRAPHQL
            ,
            $schemaString
        );

        $this->assertStringContainsString(/** @lang GraphQL */ <<<'GRAPHQL'
"""A paginated list of User edges."""
type UserConnection {
  """Pagination information about the list of edges."""
  pageInfo: PageInfo!

  """A list of User edges."""
  edges: [UserEdge!]!
}
GRAPHQL
            ,
            $schemaString
        );

        $this->assertStringContainsString(/** @lang GraphQL */ <<<'GRAPHQL'
"""An edge that contains a node of type User and a cursor."""
type UserEdge {
  """The User node."""
  node: User!

  """A unique cursor that can be used for pagination."""
  cursor: String!
}
GRAPHQL
            ,
            $schemaString
        );
    }

    public function testAliasRelayToConnection(): void
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
            usersPaginated: [User!]! @paginate
            usersConnection: [User!]! @paginate(type: CONNECTION)
            usersRelay: [User!]! @paginate(type: "relay")
            usersSimplePaginated: [User!]! @paginate(type: "simple")
        }

        type Query {
            usersPaginated: [User!]! @paginate
            usersConnection: [User!]! @paginate(type: CONNECTION)
            usersRelay: [User!]! @paginate(type: "relay")
            usersSimplePaginated: [User!]! @paginate(type: "simple")
        }
        ');
        $typeMap = $schema->getTypeMap();

        $this->assertArrayHasKey(
            'UserPaginator',
            $typeMap
        );

        $this->assertArrayHasKey(
            'UserSimplePaginator',
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
                defaultSimple: [User!]! @paginate(type: SIMPLE)
                customPaginated:  [User!]! @paginate(maxCount: 10)
                customRelay:  [User!]! @paginate(maxCount: 10, type: CONNECTION)
                customSimple:  [User!]! @paginate(maxCount: 10, type: SIMPLE)
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
            'Limits number of fetched items. Maximum allowed value: 5.',
            $defaultPaginatedAmountArg->description
        );

        $defaultRelayFirstArg = $queryType
            ->getField('defaultRelay')
            ->getArg('first');

        $this->assertInstanceOf(FieldArgument::class, $defaultRelayFirstArg);
        /** @var \GraphQL\Type\Definition\FieldArgument $defaultRelayFirstArg */
        $this->assertSame(
            'Limits number of fetched items. Maximum allowed value: 5.',
            $defaultRelayFirstArg->description
        );

        $defaultSimpleFirstArg = $queryType
            ->getField('defaultSimple')
            ->getArg('first');

        $this->assertInstanceOf(FieldArgument::class, $defaultSimpleFirstArg);
        /** @var \GraphQL\Type\Definition\FieldArgument $defaultSimpleFirstArg */
        $this->assertSame(
            'Limits number of fetched items. Maximum allowed value: 5.',
            $defaultSimpleFirstArg->description
        );

        $customPaginatedAmountArg = $queryType
            ->getField('customPaginated')
            ->getArg('first');

        $this->assertInstanceOf(FieldArgument::class, $customPaginatedAmountArg);
        /** @var \GraphQL\Type\Definition\FieldArgument $customPaginatedAmountArg */
        $this->assertSame(
            'Limits number of fetched items. Maximum allowed value: 10.',
            $customPaginatedAmountArg->description
        );

        $customRelayFirstArg = $queryType
            ->getField('customRelay')
            ->getArg('first');

        $this->assertInstanceOf(FieldArgument::class, $customRelayFirstArg);
        /** @var \GraphQL\Type\Definition\FieldArgument $customRelayFirstArg */
        $this->assertSame(
            'Limits number of fetched items. Maximum allowed value: 10.',
            $customRelayFirstArg->description
        );

        $customSimpleFirstArg = $queryType
            ->getField('customSimple')
            ->getArg('first');

        $this->assertInstanceOf(FieldArgument::class, $customSimpleFirstArg);
        /** @var \GraphQL\Type\Definition\FieldArgument $customSimpleFirstArg */
        $this->assertSame(
            'Limits number of fetched items. Maximum allowed value: 10.',
            $customSimpleFirstArg->description
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
            usersPaginated: [User!]! @paginate
            usersConnection: [User!]! @paginate(type: CONNECTION)
            usersSimplePaginated: [User!]! @paginate(type: SIMPLE)
        }
        ';

        $resultFromDefaultPagination = $this->graphQL(/** @lang GraphQL */ '
        {
            usersPaginated(first: 10) {
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
            usersConnection(first: 10) {
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

        $resultFromSimplePagination = $this->graphQL(/** @lang GraphQL */ '
        {
            usersSimplePaginated(first: 10) {
                data {
                    id
                    name
                }
            }
        }
        ');

        $this->assertSame(
            PaginationArgs::requestedTooManyItems(5, 10),
            $resultFromSimplePagination->json('errors.0.message')
        );
    }

    /**
     * @dataProvider nonNullPaginationResults
     */
    public function testThrowsWhenPaginationWithCountZeroIsRequested(bool $nonNullPaginationResults): void
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

        $result = $this
            ->graphQL(/** @lang GraphQL */ '
            {
                users(first: 0) {
                    data {
                        id
                    }
                }
            }
            ')
            ->assertGraphQLErrorMessage(PaginationArgs::requestedZeroOrLessItems(0));

        if (! $nonNullPaginationResults) {
            $result->assertJson([
                'data' => [
                    'users' => null,
                ],
            ]);
        }
    }

    /**
     * @return array<int, array{bool}>
     */
    public function nonNullPaginationResults(): array
    {
        return [
            [true],
            [false],
        ];
    }

    public function testDoesNotRequireModelWhenUsingBuilder(): void
    {
        $schema = $this->buildSchema(/** @lang GraphQL */ "
            type Query {
                users: [NotAnActualModelName!] @paginate(builder: \"{$this->qualifyTestResolver('testDoesNotRequireModelWhenUsingBuilder')}\")
            }

            type NotAnActualModelName {
                id: ID!
            }
            ");

        /** @var \GraphQL\Type\Definition\ObjectType $paginator */
        $paginator = $schema->getType('NotAnActualModelNamePaginator');
        $this->assertInstanceOf(ObjectType::class, $paginator);
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

    public function testAllowsMultiplePaginatedFieldsOfTheSameModel(): void
    {
        $schema = $this->buildSchema(/** @lang GraphQL */ '
        type Query {
            users: [User!] @paginate
            users2: [User!] @paginate
        }

        type User {
            id: ID
        }
        ');

        /** @var \GraphQL\Type\Definition\ObjectType $userPaginator */
        $userPaginator = $schema->getType('UserPaginator');

        /** @var \GraphQL\Language\AST\ObjectTypeDefinitionNode $ast */
        $ast = $userPaginator->astNode;

        $this->assertCount(1, $ast->directives);
    }
}
