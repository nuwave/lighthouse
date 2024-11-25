<?php declare(strict_types=1);

namespace Tests\Unit\Pagination;

use GraphQL\Language\AST\ObjectTypeDefinitionNode;
use GraphQL\Type\Definition\Argument;
use GraphQL\Type\Definition\FieldDefinition;
use GraphQL\Type\Definition\ObjectType;
use GraphQL\Utils\SchemaPrinter;
use GraphQL\Validator\Rules\QueryComplexity;
use Illuminate\Contracts\Config\Repository as ConfigRepository;
use Illuminate\Pagination\LengthAwarePaginator;
use Nuwave\Lighthouse\Exceptions\DefinitionException;
use Nuwave\Lighthouse\Pagination\PaginationArgs;
use Nuwave\Lighthouse\Pagination\PaginationType;
use Tests\TestCase;

final class PaginateDirectiveTest extends TestCase
{
    public function testIncludesPaginatorInfoTypeInSchema(): void
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
        $schemaString = SchemaPrinter::doPrint($schema);

        $this->assertStringContainsString(/** @lang GraphQL */ <<<'GRAPHQL'
"Information about pagination using a fully featured paginator."
type PaginatorInfo {
  "Number of items in the current page."
  count: Int!

  "Index of the current page."
  currentPage: Int!

  "Index of the first item in the current page."
  firstItem: Int

  "Are there more pages after this one?"
  hasMorePages: Boolean!

  "Index of the last item in the current page."
  lastItem: Int

  "Index of the last available page."
  lastPage: Int!

  "Number of items per page."
  perPage: Int!

  "Number of total available items."
  total: Int!
}
GRAPHQL
            ,
            $schemaString,
        );
    }

    public function testIncludesSimplePaginatorInfoTypeInSchema(): void
    {
        $schema = $this->buildSchemaWithPlaceholderQuery(/** @lang GraphQL */ '
        type User {
            id: ID!
            name: String!
        }

        extend type Query {
            users: [User!]! @paginate(type: SIMPLE)
        }
        ');
        $schemaString = SchemaPrinter::doPrint($schema);

        $this->assertStringContainsString(/** @lang GraphQL */ <<<'GRAPHQL'
"Information about pagination using a simple paginator."
type SimplePaginatorInfo {
  "Number of items in the current page."
  count: Int!

  "Index of the current page."
  currentPage: Int!

  "Index of the first item in the current page."
  firstItem: Int

  "Index of the last item in the current page."
  lastItem: Int

  "Number of items per page."
  perPage: Int!

  "Are there more pages after this one?"
  hasMorePages: Boolean!
}
GRAPHQL
            ,
            $schemaString,
        );
    }

    public function testIncludesPageInfoTypeInSchema(): void
    {
        $schema = $this->buildSchemaWithPlaceholderQuery(/** @lang GraphQL */ '
        type User {
            id: ID!
            name: String!
        }

        extend type Query {
            users: [User!]! @paginate(type: CONNECTION)
        }
        ');
        $schemaString = SchemaPrinter::doPrint($schema);

        $this->assertStringContainsString(/** @lang GraphQL */ <<<'GRAPHQL'
"Information about pagination using a Relay style cursor connection."
type PageInfo {
  "When paginating forwards, are there more items?"
  hasNextPage: Boolean!

  "When paginating backwards, are there more items?"
  hasPreviousPage: Boolean!

  "The cursor to continue paginating backwards."
  startCursor: String

  "The cursor to continue paginating forwards."
  endCursor: String

  "Total number of nodes in the paginated connection."
  total: Int!

  "Number of nodes in the current page."
  count: Int!

  "Index of the current page."
  currentPage: Int!

  "Index of the last available page."
  lastPage: Int!
}
GRAPHQL
            ,
            $schemaString,
        );
    }

    public function testDoesntIncludePaginationInfoObjectsInSchemaIfNotNeeded(): void
    {
        $schema = $this->buildSchemaWithPlaceholderQuery('');
        $typeMap = $schema->getTypeMap();

        $this->assertArrayNotHasKey('PageInfo', $typeMap);
        $this->assertArrayNotHasKey('SimplePaginatorInfo', $typeMap);
        $this->assertArrayNotHasKey('PaginatorInfo', $typeMap);
    }

    public function testManipulatesPaginator(): void
    {
        $schema = $this->buildSchema(/** @lang GraphQL */ '
        type User {
            id: ID!
        }

        type Query {
            users: [User!]! @paginate
        }
        ');
        $schemaString = SchemaPrinter::doPrint($schema);

        $this->assertStringContainsString(/** @lang GraphQL */ <<<GRAPHQL
type Query {
  users(
    "Limits number of fetched items."
    first: Int!

    "The offset from which items are returned."
    page: Int
  ): UserPaginator!
}
GRAPHQL
            ,
            $schemaString,
        );

        $this->assertStringContainsString(/** @lang GraphQL */ <<<'GRAPHQL'
"A paginated list of User items."
type UserPaginator {
  "Pagination information about the list of items."
  paginatorInfo: PaginatorInfo!

  "A list of User items."
  data: [User!]!
}
GRAPHQL
            ,
            $schemaString,
        );
    }

    public function testManipulatesSimplePaginator(): void
    {
        $schema = $this->buildSchema(/** @lang GraphQL */ '
        type User {
            id: ID!
        }

        type Query {
            users: [User!]! @paginate(type: SIMPLE)
        }
        ');
        $schemaString = SchemaPrinter::doPrint($schema);

        $this->assertStringContainsString(/** @lang GraphQL */ <<<GRAPHQL
type Query {
  users(
    "Limits number of fetched items."
    first: Int!

    "The offset from which items are returned."
    page: Int
  ): UserSimplePaginator!
}
GRAPHQL
            ,
            $schemaString,
        );

        $this->assertStringContainsString(/** @lang GraphQL */ <<<'GRAPHQL'
"A paginated list of User items."
type UserSimplePaginator {
  "Pagination information about the list of items."
  paginatorInfo: SimplePaginatorInfo!

  "A list of User items."
  data: [User!]!
}
GRAPHQL
            ,
            $schemaString,
        );
    }

    public function testManipulatesConnection(): void
    {
        $schema = $this->buildSchema(/** @lang GraphQL */ '
        type User {
            id: ID!
        }

        type Query {
            users: [User!]! @paginate(type: CONNECTION)
        }
        ');
        $schemaString = SchemaPrinter::doPrint($schema);

        $this->assertStringContainsString(/** @lang GraphQL */ <<<GRAPHQL
type Query {
  users(
    "Limits number of fetched items."
    first: Int!

    "A cursor after which elements are returned."
    after: String
  ): UserConnection!
}
GRAPHQL
            ,
            $schemaString,
        );

        $this->assertStringContainsString(/** @lang GraphQL */ <<<'GRAPHQL'
"A paginated list of User edges."
type UserConnection {
  "Pagination information about the list of edges."
  pageInfo: PageInfo!

  "A list of User edges."
  edges: [UserEdge!]!
}
GRAPHQL
            ,
            $schemaString,
        );

        $this->assertStringContainsString(/** @lang GraphQL */ <<<'GRAPHQL'
"An edge that contains a node of type User and a cursor."
type UserEdge {
  "The User node."
  node: User!

  "A unique cursor that can be used for pagination."
  cursor: String!
}
GRAPHQL
            ,
            $schemaString,
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

        $queryType = $schema->getQueryType();
        assert($queryType instanceof ObjectType);

        return $queryType->getField('users');
    }

    public function testOnlyRegistersOneTypeForMultiplePaginators(): void
    {
        $schema = $this->buildSchema(/** @lang GraphQL */ '
        type User {
            name: String
            usersPaginated: [User!]! @paginate
            usersConnection: [User!]! @paginate(type: CONNECTION)
            usersSimplePaginated: [User!]! @paginate(type: SIMPLE)
        }

        type Query {
            usersPaginated: [User!]! @paginate
            usersConnection: [User!]! @paginate(type: CONNECTION)
            usersSimplePaginated: [User!]! @paginate(type: SIMPLE)
        }
        ');
        $typeMap = $schema->getTypeMap();

        $this->assertArrayHasKey('UserPaginator', $typeMap);
        $this->assertArrayHasKey('UserSimplePaginator', $typeMap);
        $this->assertArrayHasKey('UserConnection', $typeMap);
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

        $this->assertArrayHasKey('UserPaginator', $typeMap);

        // See https://github.com/nuwave/lighthouse/issues/387
        $this->assertArrayNotHasKey('UserPaginatorPaginator', $typeMap);
    }

    public function testHasMaxCountInGeneratedCountDescription(): void
    {
        config(['lighthouse.pagination.max_count' => 5]);

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
        assert($queryType instanceof ObjectType);

        $defaultPaginatedAmountArg = $queryType
            ->getField('defaultPaginated')
            ->getArg('first');
        assert($defaultPaginatedAmountArg instanceof Argument);
        $this->assertSame('Limits number of fetched items. Maximum allowed value: 5.', $defaultPaginatedAmountArg->description);

        $defaultRelayFirstArg = $queryType
            ->getField('defaultRelay')
            ->getArg('first');
        assert($defaultRelayFirstArg instanceof Argument);
        $this->assertSame('Limits number of fetched items. Maximum allowed value: 5.', $defaultRelayFirstArg->description);

        $defaultSimpleFirstArg = $queryType
            ->getField('defaultSimple')
            ->getArg('first');
        assert($defaultSimpleFirstArg instanceof Argument);
        $this->assertSame('Limits number of fetched items. Maximum allowed value: 5.', $defaultSimpleFirstArg->description);

        $customPaginatedAmountArg = $queryType
            ->getField('customPaginated')
            ->getArg('first');
        assert($customPaginatedAmountArg instanceof Argument);
        $this->assertSame('Limits number of fetched items. Maximum allowed value: 10.', $customPaginatedAmountArg->description);

        $customRelayFirstArg = $queryType
            ->getField('customRelay')
            ->getArg('first');
        assert($customRelayFirstArg instanceof Argument);
        $this->assertSame('Limits number of fetched items. Maximum allowed value: 10.', $customRelayFirstArg->description);

        $customSimpleFirstArg = $queryType
            ->getField('customSimple')
            ->getArg('first');
        assert($customSimpleFirstArg instanceof Argument);
        $this->assertSame('Limits number of fetched items. Maximum allowed value: 10.', $customSimpleFirstArg->description);
    }

    public function testIsLimitedByMaxCountFromDirective(): void
    {
        config(['lighthouse.pagination.max_count' => 5]);

        $this->schema = /** @lang GraphQL */ '
        type User {
            id: ID!
            name: String!
        }

        type Query {
            users: [User!]! @paginate(maxCount: 6)
        }
        ';

        $result = $this->graphQL(/** @lang GraphQL */ '
        {
            users(first: 10) {
                data {
                    id
                    name
                }
            }
        }
        ');

        $this->assertSame(
            PaginationArgs::requestedTooManyItems(6, 10),
            $result->json('errors.0.message'),
        );
    }

    public function testIsLimitedByMaxCountFromDirectiveWithResolver(): void
    {
        config(['lighthouse.pagination.max_count' => 5]);

        $this->schema = /** @lang GraphQL */ <<<GRAPHQL
        type User {
            id: ID!
            name: String!
        }

        type Query {
            users: [User!]! @paginate(maxCount: 6, resolver: "{$this->qualifyTestResolver('returnPaginatedDataInsteadOfBuilder')}")
        }
        GRAPHQL;

        $result = $this->graphQL(/** @lang GraphQL */ '
        {
            users(first: 10) {
                data {
                    id
                    name
                }
            }
        }
        ');

        $this->assertSame(
            PaginationArgs::requestedTooManyItems(6, 10),
            $result->json('errors.0.message'),
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
            $resultFromDefaultPagination->json('errors.0.message'),
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
            $resultFromRelayPagination->json('errors.0.message'),
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
            $resultFromSimplePagination->json('errors.0.message'),
        );
    }

    public function testCountExplicitlyRequiredFromDirective(): void
    {
        config(['lighthouse.pagination.default_count' => 2]);

        $this->schema = /** @lang GraphQL */ '
        type User {
            id: ID!
            name: String!
        }

        type Query {
            users: [User!]! @paginate(defaultCount: null)
        }
        ';

        $this
            ->graphQL(/** @lang GraphQL */ '
            {
                users {
                    data {
                        id
                    }
                }
            }
            ')
            ->assertGraphQLErrorMessage('Field "users" argument "first" of type "Int!" is required but not provided.');
    }

    public function testThrowsWhenPaginationWithNegativeCountIsRequested(): void
    {
        $this->schema = /** @lang GraphQL */ '
        type User {
            id: ID!
            name: String!
        }

        type Query {
            users: [User!]! @paginate
        }
        ';

        $this
            ->graphQL(/** @lang GraphQL */ '
            {
                users(first: -1) {
                    data {
                        id
                    }
                }
            }
            ')
            ->assertGraphQLErrorMessage(PaginationArgs::requestedLessThanZeroItems(-1));
    }

    public function testDoesNotRequireModelWhenUsingBuilder(): void
    {
        $schema = $this->buildSchema(/** @lang GraphQL */ "
            type Query {
                users: [NotAnActualModelName!]! @paginate(builder: \"{$this->qualifyTestResolver('testDoesNotRequireModelWhenUsingBuilder')}\")
            }

            type NotAnActualModelName {
                id: ID!
            }
            ");

        $paginator = $schema->getType('NotAnActualModelNamePaginator');
        $this->assertInstanceOf(ObjectType::class, $paginator);
    }

    public function testThrowsIfBuilderIsNotPresent(): void
    {
        $this->expectExceptionObject(new DefinitionException('Failed to find class NonexistingClass in namespaces [] for directive @paginate.'));

        $this->buildSchema(/** @lang GraphQL */ '
        type Query {
            users: [Query!]! @paginate(builder: "NonexistingClass@notFound")
        }
        ');
    }

    public function testAllowsMultiplePaginatedFieldsOfTheSameModel(): void
    {
        $schema = $this->buildSchema(/** @lang GraphQL */ '
        type Query {
            users: [User!]! @paginate
            users2: [User!]! @paginate
        }

        type User {
            id: ID
        }
        ');

        $userPaginator = $schema->getType('UserPaginator');
        assert($userPaginator instanceof ObjectType);

        $ast = $userPaginator->astNode;
        assert($ast instanceof ObjectTypeDefinitionNode);

        $this->assertCount(1, $ast->directives);
    }

    public function testDisallowFirstNull(): void
    {
        $this->schema = /** @lang GraphQL */ '
        type User {
            id: ID!
        }

        type Query {
            users: [User!]! @paginate(defaultCount: 2)
        }
        ';

        $this->graphQL(/** @lang GraphQL */ '
        {
            users(first: null) {
                data {
                    id
                }
            }
        }
        ')->assertGraphQLErrorMessage('Expected value of type "Int!", found null.');
    }

    public function testQueriesFirst0SimplePaginator(): void
    {
        $this->schema = /** @lang GraphQL */ '
        type User {
            id: ID!
        }

        type Query {
            users: [User!]! @paginate
        }
        ';

        $this->graphQL(/** @lang GraphQL */ '
        {
            users(first: 0) {
                data {
                    id
                }
            }
        }
        ')->assertExactJson([
            'data' => [
                'users' => [
                    'data' => [],
                ],
            ],
        ]);
    }

    /**
     * @param  array{first: int}  $args
     *
     * @return \Illuminate\Pagination\LengthAwarePaginator<array{id: int}>
     */
    public static function returnPaginatedDataInsteadOfBuilder(mixed $root, array $args): LengthAwarePaginator
    {
        return new LengthAwarePaginator([
            [
                'id' => 1,
            ],
            [
                'id' => 2,
            ],
        ], 2, $args['first']);
    }

    public function testPaginatorResolver(): void
    {
        $this->buildSchema(/* @lang GraphQL */ "
        type Query {
            users: [User!]! @paginate(resolver: \"{$this->qualifyTestResolver('returnPaginatedDataInsteadOfBuilder')}\")
        }

        type User {
            id: ID
        }
        ");

        $this->graphQL(/* @lang GraphQL */ '
        {
            users(first: 5) {
                paginatorInfo {
                    perPage
                }
                data {
                    id
                }
            }
        }
        ')->assertJson([
            'data' => [
                'users' => [
                    'paginatorInfo' => [
                        'perPage' => 5,
                    ],
                    'data' => [
                        ['id' => 1],
                        ['id' => 2],
                    ],
                ],
            ],
        ]);
    }

    public function testThrowsIfResolverIsNotPresent(): void
    {
        $this->expectExceptionObject(new DefinitionException('Failed to find class NonexistingClass in namespaces [] for directive @paginate.'));

        $this->buildSchema(/** @lang GraphQL */ '
        type Query {
            users: [Query!]! @paginate(resolver: "NonexistingClass@notFound")
        }
        ');
    }

    public function testCustomizeQueryComplexityResolver(): void
    {
        $max = 42;
        $this->setMaxQueryComplexity($max);

        $this->buildSchema(/* @lang GraphQL */ "
        type Query {
            users(complexity: Int!): [User!]! @paginate(complexityResolver: \"{$this->qualifyTestResolver('complexityResolver')}\")
        }

        type User {
            id: ID
        }
        ");

        $complexity = 123;
        $this->graphQL(/* @lang GraphQL */ '
        query ($complexity: Int!) {
            users(first: 5, complexity: $complexity) {
                data {
                    id
                }
            }
        }
        ', [
            'complexity' => $complexity,
        ])->assertGraphQLErrorMessage(QueryComplexity::maxQueryComplexityErrorMessage($max, $complexity));
    }

    /** @param  array{complexity: int}  $args */
    public static function complexityResolver(int $childrenComplexity, array $args): int
    {
        return $args['complexity'];
    }

    protected function setMaxQueryComplexity(int $max): void
    {
        $config = $this->app->make(ConfigRepository::class);
        $config->set('lighthouse.security.max_query_complexity', $max);
    }
}
