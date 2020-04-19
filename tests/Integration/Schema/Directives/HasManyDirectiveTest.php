<?php

namespace Tests\Integration\Schema\Directives;

use GraphQL\Error\Error;
use Illuminate\Support\Arr;
use Nuwave\Lighthouse\Pagination\PaginationArgs;
use Tests\DBTestCase;
use Tests\Utils\Models\Post;
use Tests\Utils\Models\Task;
use Tests\Utils\Models\User;

class HasManyDirectiveTest extends DBTestCase
{
    /**
     * The authenticated user.
     *
     * @var \Tests\Utils\Models\User
     */
    protected $user;

    /**
     * The authenticated user's tasks.
     *
     * @var \Illuminate\Support\Collection
     */
    protected $tasks;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = factory(User::class)->create();
        $this->tasks = factory(Task::class, 3)->create([
            'user_id' => $this->user->getKey(),
        ]);
        factory(Task::class)->create([
            'user_id' => $this->user->getKey(),
            // This task should be ignored via global scope on the Task model
            'name' => 'cleaning',
        ]);

        $this->be($this->user);
    }

    public function testCanQueryHasManyRelationship(): void
    {
        $this->schema = /** @lang GraphQL */ '
        type User {
            tasks: [Task!]! @hasMany
        }

        type Task {
            id: Int
            foo: String
        }

        type Query {
            user: User @auth
        }
        ';

        $tasksWithoutGlobalScope = $this->user
            ->tasks()
            ->withoutGlobalScope('no_cleaning')
            ->count();
        $this->assertSame(4, $tasksWithoutGlobalScope);

        // Ensure global scopes are respected here
        $this->graphQL(/** @lang GraphQL */ '
        {
            user {
                tasks {
                    id
                }
            }
        }
        ')->assertJsonCount(3, 'data.user.tasks');
    }

    public function testCallsScopeWithResolverArgs(): void
    {
        $this->assertCount(3, $this->user->tasks);

        $this->schema = /** @lang GraphQL */ '
        type User {
            tasks(foo: Int): [Task!]! @hasMany(scopes: ["foo"])
        }

        type Task {
            id: Int
            foo: String
        }

        type Query {
            user: User @auth
        }
        ';

        $this->graphQL(/** @lang GraphQL */ '
        {
            user {
                tasks(foo: 2) {
                    id
                }
            }
        }
        ')->assertJsonCount(2, 'data.user.tasks');
    }

    public function testCanQueryHasManyPaginator(): void
    {
        $this->schema = /** @lang GraphQL */ '
        type User {
            tasks: [Task!]! @hasMany(type: "paginator")
            posts: [Post!]! @hasMany(type: "paginator")
        }

        type Task {
            id: Int!
        }

        type Post {
            id: Int!
        }

        type Query {
            user: User @auth
        }
        ';

        $this->graphQL(/** @lang GraphQL */ '
        {
            user {
                tasks(first: 2) {
                    paginatorInfo {
                        count
                        hasMorePages
                        total
                    }
                    data {
                        id
                    }
                }
            }
        }
        ')->assertJson([
            'data' => [
                'user' => [
                    'tasks' => [
                        'paginatorInfo' => [
                            'count' => 2,
                            'hasMorePages' => true,
                            'total' => 3,
                        ],
                    ],
                ],
            ],
        ])->assertJsonCount(2, 'data.user.tasks.data');
    }

    public function testDoesNotRequireModelClassForPaginatedHasMany(): void
    {
        $this->schema = /** @lang GraphQL */ '
        type User {
            tasks: [NotTheModelNameTask!]! @hasMany(type: "paginator")
        }

        type NotTheModelNameTask {
            id: Int!
        }

        type Query {
            user: User @auth
        }
        ';

        $this->graphQL(/** @lang GraphQL */ '
        {
            user {
                tasks(first: 2) {
                    paginatorInfo {
                        count
                        hasMorePages
                        total
                    }
                    data {
                        id
                    }
                }
            }
        }
        ')->assertJson([
            'data' => [
                'user' => [
                    'tasks' => [
                        'paginatorInfo' => [
                            'count' => 2,
                            'hasMorePages' => true,
                            'total' => 3,
                        ],
                    ],
                ],
            ],
        ])->assertJsonCount(2, 'data.user.tasks.data');
    }

    public function testPaginatorTypeIsLimitedByMaxCountFromDirective(): void
    {
        config(['lighthouse.paginate_max_count' => 1]);

        $this->schema = /** @lang GraphQL */ '
        type User {
            tasks: [Task!]! @hasMany(type: "paginator", maxCount: 3)
        }

        type Task {
            id: Int!
        }

        type Query {
            user: User @auth
        }
        ';

        $result = $this->graphQL(/** @lang GraphQL */ '
        {
            user {
                tasks(first: 5) {
                    data {
                        id
                    }
                }
            }
        }
        ');

        $this->assertSame(
            PaginationArgs::requestedTooManyItems(3, 5),
            $result->jsonGet('errors.0.message')
        );
    }

    public function testHandlesPaginationWithCountZero(): void
    {
        $this->schema = /** @lang GraphQL */ '
        type User {
            id: ID
            tasks: [Task!] @hasMany(type: "paginator")
        }

        type Task {
            id: Int!
        }

        type Query {
            user: User @auth
        }
        ';

        $this->graphQL(/** @lang GraphQL */ '
        {
            user {
                id
                tasks(first: 0) {
                    data {
                        id
                    }
                }
            }
        }
        ')->assertJson([
            'data' => [
                'user' => [
                    'id' => $this->user->id,
                    'tasks' => null,
                ],
            ],
        ])->assertGraphQLErrorCategory(Error::CATEGORY_GRAPHQL);
    }

    public function testRelayTypeIsLimitedByMaxCountFromDirective(): void
    {
        config(['lighthouse.paginate_max_count' => 1]);

        $this->schema = /** @lang GraphQL */ '
        type User {
            tasks: [Task!]! @hasMany(type: "relay", maxCount: 3)
        }

        type Task {
            id: Int!
        }

        type Query {
            user: User @auth
        }
        ';

        $result = $this->graphQL(/** @lang GraphQL */ '
        {
            user {
                tasks(first: 5) {
                    edges {
                        node {
                            id
                        }
                    }
                }
            }
        }
        ');

        $this->assertSame(
            PaginationArgs::requestedTooManyItems(3, 5),
            $result->jsonGet('errors.0.message')
        );
    }

    public function testPaginatorTypeIsLimitedToMaxCountFromConfig(): void
    {
        config(['lighthouse.paginate_max_count' => 2]);

        $this->schema = /** @lang GraphQL */ '
        type User {
            tasks: [Task!]! @hasMany(type: "paginator")
        }

        type Task {
            id: Int!
        }

        type Query {
            user: User @auth
        }
        ';

        $result = $this->graphQL(/** @lang GraphQL */ '
        {
            user {
                tasks(first: 3) {
                    data {
                        id
                    }
                }
            }
        }
        ');

        $this->assertSame(
            PaginationArgs::requestedTooManyItems(2, 3),
            $result->jsonGet('errors.0.message')
        );
    }

    public function testRelayTypeIsLimitedToMaxCountFromConfig(): void
    {
        config(['lighthouse.paginate_max_count' => 2]);

        $this->schema = /** @lang GraphQL */'
        type User {
            tasks: [Task!]! @hasMany(type: "relay")
        }

        type Task {
            id: Int!
        }

        type Query {
            user: User @auth
        }
        ';

        $result = $this->graphQL(/** @lang GraphQL */ '
        {
            user {
                tasks(first: 3) {
                    edges {
                        node {
                           id
                       }
                   }
                }
            }
        }
        ');

        $this->assertSame(
            PaginationArgs::requestedTooManyItems(2, 3),
            $result->jsonGet('errors.0.message')
        );
    }

    public function testUsesEdgeTypeForRelayConnections(): void
    {
        $this->schema = /** @lang GraphQL */ '
        type User {
            tasks: [Task!]! @hasMany (
                type: "relay"
                edgeType: "TaskEdge"
            )
        }

        type Task {
            id: Int
            foo: String
        }

        type TaskEdge {
            cursor: String!
            node: Task!
        }

        type Query {
            user: User @auth
        }
        ';

        $expectedConnectionName = 'TaskEdgeConnection';

        $this->assertNotEmpty(
            $this->introspectType($expectedConnectionName)
        );

        $user = $this->introspectType('User');
        $tasks = Arr::first(
            $user['fields'],
            function (array $user): bool {
                return $user['name'] === 'tasks';
            }
        );
        $this->assertSame(
            $expectedConnectionName,
            $tasks['type']['name']
        );
    }

    public function testCanQueryHasManyPaginatorWithADefaultCount(): void
    {
        $this->schema = /** @lang GraphQL */ '
        type User {
            tasks: [Task!]! @hasMany(type: "paginator", defaultCount: 2)
        }

        type Task {
            id: Int!
        }

        type Query {
            user: User @auth
        }
        ';

        $this->graphQL(/** @lang GraphQL */ '
        {
            user {
                tasks {
                    paginatorInfo {
                        count
                        hasMorePages
                        total
                    }
                    data {
                        id
                    }
                }
            }
        }
        ')->assertJson([
            'data' => [
                'user' => [
                    'tasks' => [
                        'paginatorInfo' => [
                            'count' => 2,
                            'hasMorePages' => true,
                            'total' => 3,
                        ],
                    ],
                ],
            ],
        ])->assertJsonCount(2, 'data.user.tasks.data');
    }

    public function testCanQueryHasManyRelayConnection(): void
    {
        $this->schema = /** @lang GraphQL */ '
        type User {
            tasks: [Task!]! @hasMany(type: "relay")
        }

        type Task {
            id: Int!
        }

        type Query {
            user: User @auth
        }
        ';

        $this->graphQL(/** @lang GraphQL */ '
        {
            user {
                tasks(first: 2) {
                    pageInfo {
                        hasNextPage
                    }
                    edges {
                        node {
                            id
                        }
                    }
                }
            }
        }
        ')->assertJson([
            'data' => [
                'user' => [
                    'tasks' => [
                        'pageInfo' => [
                            'hasNextPage' => true,
                        ],
                    ],
                ],
            ],
        ])->assertJsonCount(2, 'data.user.tasks.edges');
    }

    public function testCanQueryHasManyRelayConnectionWithADefaultCount(): void
    {
        $this->schema = /** @lang GraphQL */ '
        type User {
            tasks: [Task!]! @hasMany(type: "relay", defaultCount: 2)
        }

        type Task {
            id: Int!
        }

        type Query {
            user: User @auth
        }
        ';

        $this->graphQL(/** @lang GraphQL */ '
        {
            user {
                tasks {
                    pageInfo {
                        hasNextPage
                    }
                    edges {
                        node {
                            id
                        }
                    }
                }
            }
        }
        ')->assertJson([
            'data' => [
                'user' => [
                    'tasks' => [
                        'pageInfo' => [
                            'hasNextPage' => true,
                        ],
                    ],
                ],
            ],
        ])->assertJsonCount(2, 'data.user.tasks.edges');
    }

    public function testCanQueryHasManyNestedRelationships(): void
    {
        $this->schema = /** @lang GraphQL */ '
        type User {
            tasks: [Task!]! @hasMany(type: "relay")
        }

        type Task {
            id: Int!
            user: User @belongsTo
        }

        type Query {
            user: User @auth
        }
        ';

        $this->graphQL(/** @lang GraphQL */ '
        {
            user {
                tasks(first: 2) {
                    pageInfo {
                        hasNextPage
                    }
                    edges {
                        node {
                            id
                            user {
                                tasks(first: 2) {
                                    edges {
                                        node {
                                            id
                                        }
                                    }
                                }
                            }
                        }
                    }
                }
            }
        }
        ')->assertJson([
            'data' => [
                'user' => [
                    'tasks' => [
                        'pageInfo' => [
                            'hasNextPage' => true,
                        ],
                    ],
                ],
            ],
        ])->assertJsonCount(2, 'data.user.tasks.edges')
        ->assertJsonCount(2, 'data.user.tasks.edges.0.node.user.tasks.edges');
    }

    public function testCanQueryHasManySelfReferencingRelationships(): void
    {
        $post1 = factory(Post::class)->create([
            'id' => 1,
        ]);

        $post2 = factory(Post::class)->create([
            'id' => 2,
            'parent_id' => $post1->getKey(),
        ]);

        $post3 = factory(Post::class)->create([
            'id' => 3,
            'parent_id' => $post2->getKey(),
        ]);

        $this->schema = /** @lang GraphQL */ '
        type Post {
            id: Int!
            parent: Post @belongsTo
        }

        type Query {
            posts: [Post!]! @all
        }
        ';

        $this->graphQL(/** @lang GraphQL */ '
        {
            posts {
                id
                parent {
                    id
                    parent {
                        id
                    }
                }
            }
        }
        ')->assertJson([
            'data' => [
                'posts' => [
                    [
                        'id' => 1,
                        'parent' => null,
                    ],
                    [
                        'id' => 2,
                        'parent' => [
                            'id' => 1,
                            'parent' => null,
                        ],
                    ],
                    [
                        'id' => 3,
                        'parent' => [
                            'id' => 2,
                            'parent' => [
                                'id' => 1,
                            ],
                        ],
                    ],
                ],
            ],
        ]);
    }

    public function testThrowsErrorWithUnknownTypeArg(): void
    {
        $this->expectExceptionMessageRegExp('/^Found invalid pagination type/');

        $schema = $this->buildSchemaWithPlaceholderQuery(/** @lang GraphQL */ '
        type User {
            tasks(first: Int! after: Int): [Task!]! @hasMany(type:"foo")
        }

        type Task {
            foo: String
        }
        ');

        $type = $schema->getType('User');
        $type->config['fields']();
    }

    public function testCanQueryHasManyPaginatorBeforeQuery(): void
    {
        // BeforeQuery
        $this->schema = /** @lang GraphQL */ '
        type User {
            id: Int!
            tasks: [Task!]! @hasMany(type: "paginator")
        }

        type Task {
            id: Int!
        }

        type Query {
            user(id: ID! @eq): User @find
            tasks: [Task!]! @paginate
        }
        ';

        $this->graphQL(/** @lang GraphQL */ '
        {
            tasks(first: 2) {
                data {
                    id
                }
            }
        }
        ')->assertJsonCount(2, 'data.tasks.data');
    }

    public function testCanQueryHasManyPaginatorAfterQuery(): void
    {
        // AfterQuery
        $this->schema = /** @lang GraphQL */ '
        type Query {
            user(id: ID! @eq): User @find
            tasks: [Task!]! @paginate
        }

        type User {
            id: Int!
            tasks: [Task!]! @hasMany(type: "paginator")
        }

        type Task {
            id: Int!
        }
        ';

        $this->graphQL(/** @lang GraphQL */ '
        {
            tasks(first: 2) {
                data{
                    id
                }
            }
        }
        ')->assertJsonCount(2, 'data.tasks.data');
    }

    public function testCanQueryHasManyNoTypePaginator(): void
    {
        // AfterQuery
        $this->schema = /** @lang GraphQL */ '
        type Query {
            user(id: ID! @eq): User @find
            tasks: [Task!]! @paginate
        }

        type User {
            id: Int!
            tasks: [Task!]! @hasMany
        }

        type Task {
            id: Int!
        }
        ';

        $this->graphQL(/** @lang GraphQL */ '
        {
            tasks(first: 2) {
                data{
                    id
                }
            }
        }
        ')->assertJsonCount(2, 'data.tasks.data');
    }
}
