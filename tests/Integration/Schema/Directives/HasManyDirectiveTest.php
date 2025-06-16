<?php declare(strict_types=1);

namespace Tests\Integration\Schema\Directives;

use Nuwave\Lighthouse\Pagination\PaginationArgs;
use Tests\DBTestCase;
use Tests\Utils\Models\Post;
use Tests\Utils\Models\Role;
use Tests\Utils\Models\Task;
use Tests\Utils\Models\User;
use Tests\Utils\Policies\UserPolicy;

final class HasManyDirectiveTest extends DBTestCase
{
    public function testQueryHasManyRelationship(): void
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
            user: User @first
        }
        ';

        $user = factory(User::class)->create();
        assert($user instanceof User);

        $tasks = factory(Task::class, 3)->make();
        $user->tasks()->saveMany($tasks);

        $ignoredViaGlobalScope = factory(Task::class)->make();
        assert($ignoredViaGlobalScope instanceof Task);
        $ignoredViaGlobalScope->name = Task::CLEANING;
        $user->tasks()->save($ignoredViaGlobalScope);

        $tasksWithoutGlobalScope = $user
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

    public function testHasManyWithRenamedModel(): void
    {
        $this->schema = /** @lang GraphQL */ '
        type User {
            foos: [Foo!]! @hasMany(relation: "tasks")
        }

        type Foo @model(class: "Task") {
            id: Int
        }

        type Query {
            user: User @first
        }
        ';

        $user = factory(User::class)->create();
        assert($user instanceof User);

        $tasks = factory(Task::class, 3)->make();
        $user->tasks()->saveMany($tasks);

        $this->graphQL(/** @lang GraphQL */ '
        {
            user {
                foos {
                    id
                }
            }
        }
        ')->assertJsonCount(3, 'data.user.foos');
    }

    public function testQueryHasManyWithCondition(): void
    {
        $this->schema = /** @lang GraphQL */ '
        type User {
            tasks(
                id: ID! @eq
            ): [Task!]! @hasMany
        }

        type Task {
            id: Int!
        }

        type Query {
            user: User! @first
        }
        ';

        $user = factory(User::class)->create();
        assert($user instanceof User);

        $tasks = factory(Task::class, 3)->make();
        $user->tasks()->saveMany($tasks);

        $firstTask = $tasks->first();
        assert($firstTask instanceof Task);

        $this
            ->graphQL(/** @lang GraphQL */ '
            query ($id: ID!) {
                user {
                    tasks(id: $id) {
                        id
                    }
                }
            }
            ', [
                'id' => $firstTask->id,
            ])
            ->assertJsonCount(1, 'data.user.tasks');
    }

    public function testQueryHasManyWithConditionInDifferentAliases(): void
    {
        $this->schema = /** @lang GraphQL */ '
        type User {
            tasks(
                id: ID! @eq
            ): [Task!]! @hasMany
        }

        type Task {
            id: Int!
        }

        type Query {
            users: [User!]! @all
        }
        ';

        $user1 = factory(User::class)->create();
        assert($user1 instanceof User);

        $tasks1 = factory(Task::class, 3)->make();
        $user1->tasks()->saveMany($tasks1);

        $user2 = factory(User::class)->create();
        assert($user2 instanceof User);

        $tasks2 = factory(Task::class, 3)->make();
        $user2->tasks()->saveMany($tasks2);

        $firstTask = $tasks1->first();
        assert($firstTask instanceof Task);

        $lastTask = $tasks2->last();
        assert($lastTask instanceof Task);

        $this
            ->graphQL(/** @lang GraphQL */ '
            query ($firstId: ID!, $lastId: ID!) {
                users {
                    firstTasks: tasks(id: $firstId) {
                        id
                    }
                    lastTasks: tasks(id: $lastId) {
                        id
                    }
                }
            }
            ', [
                'firstId' => $firstTask->id,
                'lastId' => $lastTask->id,
            ])
            ->assertExactJson([
                'data' => [
                    'users' => [
                        [
                            'firstTasks' => [
                                [
                                    'id' => $firstTask->id,
                                ],
                            ],
                            'lastTasks' => [],
                        ],
                        [
                            'firstTasks' => [],
                            'lastTasks' => [
                                [
                                    'id' => $lastTask->id,
                                ],
                            ],
                        ],
                    ],
                ],
            ]);
    }

    public function testQueryPaginatedHasManyWithConditionInDifferentAliases(): void
    {
        $this->schema = /** @lang GraphQL */ '
        type User {
            tasks(
                id: ID! @eq
            ): [Task!]! @hasMany(type: PAGINATOR, defaultCount: 10)
        }

        type Task {
            id: Int!
        }

        type Query {
            users: [User!]! @all
        }
        ';

        $user1 = factory(User::class)->create();
        assert($user1 instanceof User);

        $tasks1 = factory(Task::class, 3)->make();
        $user1->tasks()->saveMany($tasks1);

        $user2 = factory(User::class)->create();
        assert($user2 instanceof User);

        $tasks2 = factory(Task::class, 3)->make();
        $user2->tasks()->saveMany($tasks2);

        $firstTask = $tasks1->first();
        assert($firstTask instanceof Task);

        $lastTask = $tasks2->last();
        assert($lastTask instanceof Task);

        $this
            ->graphQL(/** @lang GraphQL */ '
            query ($firstId: ID!, $lastId: ID!) {
                users {
                    firstTasks: tasks(id: $firstId) {
                        data {
                            id
                        }
                    }
                    lastTasks: tasks(id: $lastId) {
                        data {
                            id
                        }
                    }
                }
            }
            ', [
                'firstId' => $firstTask->id,
                'lastId' => $lastTask->id,
            ])
            ->assertExactJson([
                'data' => [
                    'users' => [
                        [
                            'firstTasks' => [
                                'data' => [
                                    [
                                        'id' => $firstTask->id,
                                    ],
                                ],
                            ],
                            'lastTasks' => [
                                'data' => [],
                            ],
                        ],
                        [
                            'firstTasks' => [
                                'data' => [],
                            ],
                            'lastTasks' => [
                                'data' => [
                                    [
                                        'id' => $lastTask->id,
                                    ],
                                ],
                            ],
                        ],
                    ],
                ],
            ]);
    }

    public function testQueryPaginatedHasManyFirst0(): void
    {
        $this->schema = /** @lang GraphQL */ '
        type User {
            id: ID!
            tasks: [Task!]! @hasMany(type: PAGINATOR)
        }

        type Task {
            id: ID!
        }

        type Query {
            users: [User!]! @all
        }
        ';

        $user = factory(User::class)->create();
        assert($user instanceof User);

        $tasksCount = 3;
        $tasks = factory(Task::class, $tasksCount)->make();
        $user->tasks()->saveMany($tasks);

        $this
            ->graphQL(/** @lang GraphQL */ '
            {
                users {
                    id
                    tasks(first: 0) {
                        paginatorInfo {
                            total
                        }
                        data {
                            id
                        }
                    }
                }
            }
            ')
            ->assertExactJson([
                'data' => [
                    'users' => [
                        [
                            'id' => (string) $user->id,
                            'tasks' => [
                                'paginatorInfo' => [
                                    'total' => $tasksCount,
                                ],
                                'data' => [],
                            ],
                        ],
                    ],
                ],
            ]);
    }

    public function testQueryPaginatedHasManyWithNonUniqueForeignKey(): void
    {
        $this->schema = /** @lang GraphQL */ '
        type Post {
            roles: [RoleUser!]! @hasMany(relation: "roles", type: PAGINATOR, defaultCount: 10)
        }

        type RoleUser {
            id: Int!
            user_id: Int!
            role_id: Int!
        }

        type Query {
            posts: [Post!]! @all
        }
        ';

        $user = factory(User::class)->create();
        assert($user instanceof User);

        $posts = factory(Post::class, 2)->make();
        foreach ($posts as $post) {
            assert($post instanceof Post);
            $post->user()->associate($user);
            $post->save();
        }

        $this->assertCount(2, $user->posts);

        $roles = factory(Role::class, 3)->make();
        foreach ($roles as $role) {
            assert($role instanceof Role);
            $user->roles()->save($role);
        }

        $this->assertCount(3, $user->roles);

        $this
            ->graphQL(/** @lang GraphQL */ '
            query {
                posts {
                    roles {
                        data {
                            id
                            user_id
                            role_id
                        }
                    }
                }
            }
            ')
            ->assertExactJson([
                'data' => [
                    'posts' => [
                        [
                            'roles' => [
                                'data' => [
                                    [
                                        'id' => 1,
                                        'user_id' => $user->id,
                                        'role_id' => $roles[0]->id,
                                    ],
                                    [
                                        'id' => 2,
                                        'user_id' => $user->id,
                                        'role_id' => $roles[1]->id,
                                    ],
                                    [
                                        'id' => 3,
                                        'user_id' => $user->id,
                                        'role_id' => $roles[2]->id,
                                    ],
                                ],
                            ],
                        ],
                        [
                            'roles' => [
                                'data' => [
                                    [
                                        'id' => 1,
                                        'user_id' => $user->id,
                                        'role_id' => $roles[0]->id,
                                    ],
                                    [
                                        'id' => 2,
                                        'user_id' => $user->id,
                                        'role_id' => $roles[1]->id,
                                    ],
                                    [
                                        'id' => 3,
                                        'user_id' => $user->id,
                                        'role_id' => $roles[2]->id,
                                    ],
                                ],
                            ],
                        ],
                    ],
                ],
            ]);
    }

    public function testCallsScopeWithResolverArgs(): void
    {
        $this->schema = /** @lang GraphQL */ '
        type User {
            tasks(foo: Int): [Task!]! @hasMany(scopes: ["foo"])
        }

        type Task {
            id: Int
            foo: String
        }

        type Query {
            user: User @first
        }
        ';

        $user = factory(User::class)->create();
        assert($user instanceof User);

        $tasks = factory(Task::class, 3)->make();
        $user->tasks()->saveMany($tasks);

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

    /** @dataProvider batchloadRelations */
    public function testQueryHasManyPaginator(bool $batchloadRelations): void
    {
        config(['lighthouse.batchload_relations' => $batchloadRelations]);

        $user = factory(User::class)->create();
        assert($user instanceof User);

        $tasks = factory(Task::class, 3)->make();
        $user->tasks()->saveMany($tasks);

        $user->posts()->saveMany(
            factory(Post::class, 3)->make(),
        );

        $this->schema = /** @lang GraphQL */ '
        type User {
            tasks: [Task!]! @hasMany(type: PAGINATOR)
            posts: [Post!]! @hasMany(type: SIMPLE)
        }

        type Task {
            id: Int!
        }

        type Post {
            id: Int!
        }

        type Query {
            user: User @first
        }
        ';

        $this
            ->graphQL(/** @lang GraphQL */ '
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
                    posts(first: 5) {
                        paginatorInfo {
                            count
                        }
                        data {
                            id
                        }
                    }
                }
            }
            ')
            ->assertJson([
                'data' => [
                    'user' => [
                        'tasks' => [
                            'paginatorInfo' => [
                                'count' => 2,
                                'hasMorePages' => true,
                                'total' => 3,
                            ],
                        ],
                        'posts' => [
                            'paginatorInfo' => [
                                'count' => 3,
                            ],
                        ],
                    ],
                ],
            ])
            ->assertJsonCount(2, 'data.user.tasks.data')
            ->assertJsonCount(3, 'data.user.posts.data');
    }

    public function testDoesNotRequireModelClassForPaginatedHasMany(): void
    {
        $user = factory(User::class)->create();
        assert($user instanceof User);

        $tasks = factory(Task::class, 3)->make();
        $user->tasks()->saveMany($tasks);

        $this->schema = /** @lang GraphQL */ '
        type User {
            tasks: [NotTheModelNameTask!]! @hasMany(type: PAGINATOR)
        }

        type NotTheModelNameTask {
            id: Int!
        }

        type Query {
            user: User @first
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
        config(['lighthouse.pagination.max_count' => 1]);

        $user = factory(User::class)->create();
        assert($user instanceof User);

        $tasks = factory(Task::class, 3)->make();
        $user->tasks()->saveMany($tasks);

        $this->schema = /** @lang GraphQL */ '
        type User {
            tasks: [Task!]! @hasMany(type: PAGINATOR, maxCount: 3)
        }

        type Task {
            id: Int!
        }

        type Query {
            user: User @first
        }
        ';

        $this
            ->graphQL(/** @lang GraphQL */ '
            {
                user {
                    tasks(first: 5) {
                        data {
                            id
                        }
                    }
                }
            }
            ')
            ->assertGraphQLErrorMessage(PaginationArgs::requestedTooManyItems(3, 5));
    }

    public function testPaginatorTypeIsUnlimitedByMaxCountFromDirective(): void
    {
        config(['lighthouse.pagination.max_count' => 1]);

        $user = factory(User::class)->create();
        assert($user instanceof User);

        $tasks = factory(Task::class, 3)->make();
        $user->tasks()->saveMany($tasks);

        $this->schema = /** @lang GraphQL */ '
        type User {
            tasks: [Task!]! @hasMany(type: PAGINATOR, maxCount: null)
        }

        type Task {
            id: Int!
        }

        type Query {
            user: User @first
        }
        ';

        $this
            ->graphQL(/** @lang GraphQL */ '
            {
                user {
                    tasks(first: 5) {
                        data {
                            id
                        }
                    }
                }
            }
            ')
            ->assertGraphQLErrorFree();
    }

    public function testRejectsPaginationWithNegativeCount(): void
    {
        $user = factory(User::class)->create();
        assert($user instanceof User);

        $tasks = factory(Task::class, 3)->make();
        $user->tasks()->saveMany($tasks);

        $this->schema = /** @lang GraphQL */ '
        type User {
            id: ID
            tasks: [Task!] @hasMany(type: PAGINATOR)
        }

        type Task {
            id: Int!
        }

        type Query {
            user: User @first
        }
        ';

        $this
            ->graphQL(/** @lang GraphQL */ '
            {
                user {
                    id
                    tasks(first: -1) {
                        data {
                            id
                        }
                    }
                }
            }
            ')
            ->assertGraphQLErrorMessage(PaginationArgs::requestedLessThanZeroItems(-1));
    }

    public function testRelayTypeIsLimitedByMaxCountFromDirective(): void
    {
        config(['lighthouse.pagination.max_count' => 1]);

        $user = factory(User::class)->create();
        assert($user instanceof User);

        $tasks = factory(Task::class, 3)->make();
        $user->tasks()->saveMany($tasks);

        $this->schema = /** @lang GraphQL */ '
        type User {
            tasks: [Task!]! @hasMany(type: CONNECTION, maxCount: 3)
        }

        type Task {
            id: Int!
        }

        type Query {
            user: User @first
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
            $result->json('errors.0.message'),
        );
    }

    public function testPaginatorTypeIsLimitedToMaxCountFromConfig(): void
    {
        config(['lighthouse.pagination.max_count' => 2]);

        $user = factory(User::class)->create();
        assert($user instanceof User);

        $tasks = factory(Task::class, 3)->make();
        $user->tasks()->saveMany($tasks);

        $this->schema = /** @lang GraphQL */ '
        type User {
            tasks: [Task!]! @hasMany(type: PAGINATOR)
        }

        type Task {
            id: Int!
        }

        type Query {
            user: User @first
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
            $result->json('errors.0.message'),
        );
    }

    public function testRelayTypeIsLimitedToMaxCountFromConfig(): void
    {
        config(['lighthouse.pagination.max_count' => 2]);

        $user = factory(User::class)->create();
        assert($user instanceof User);

        $tasks = factory(Task::class, 3)->make();
        $user->tasks()->saveMany($tasks);

        $this->schema = /** @lang GraphQL */ '
        type User {
            tasks: [Task!]! @hasMany(type: CONNECTION)
        }

        type Task {
            id: Int!
        }

        type Query {
            user: User @first
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
            $result->json('errors.0.message'),
        );
    }

    public function testQueryHasManyPaginatorWithADefaultCount(): void
    {
        $user = factory(User::class)->create();
        assert($user instanceof User);

        $tasks = factory(Task::class, 3)->make();
        $user->tasks()->saveMany($tasks);

        $this->schema = /** @lang GraphQL */ '
        type User {
            tasks: [Task!]! @hasMany(type: PAGINATOR, defaultCount: 2)
        }

        type Task {
            id: Int!
        }

        type Query {
            user: User @first
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

    public function testQueryHasManyRelayConnection(): void
    {
        $user = factory(User::class)->create();
        assert($user instanceof User);

        $tasks = factory(Task::class, 3)->make();
        $user->tasks()->saveMany($tasks);

        $this->schema = /** @lang GraphQL */ '
        type User {
            tasks: [Task!]! @hasMany(type: CONNECTION)
        }

        type Task {
            id: Int!
        }

        type Query {
            user: User @first
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

    public function testQueryHasManyRelayConnectionWithADefaultCount(): void
    {
        $user = factory(User::class)->create();
        assert($user instanceof User);

        $tasks = factory(Task::class, 3)->make();
        $user->tasks()->saveMany($tasks);

        $this->schema = /** @lang GraphQL */ '
        type User {
            tasks: [Task!]! @hasMany(type: CONNECTION, defaultCount: 2)
        }

        type Task {
            id: Int!
        }

        type Query {
            user: User @first
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

    public function testQueryHasManyNestedRelationships(): void
    {
        $user = factory(User::class)->create();
        assert($user instanceof User);

        $tasks = factory(Task::class, 3)->make();
        $user->tasks()->saveMany($tasks);

        $this->schema = /** @lang GraphQL */ '
        type User {
            tasks: [Task!]! @hasMany(type: CONNECTION)
        }

        type Task {
            id: Int!
            user: User @belongsTo
        }

        type Query {
            user: User @first
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

    public function testQueryHasManySelfReferencingRelationships(): void
    {
        $post1 = factory(Post::class)->create();
        assert($post1 instanceof Post);

        $post2 = factory(Post::class)->make();
        assert($post2 instanceof Post);
        $post2->parent()->associate($post1);
        $post2->save();

        $post3 = factory(Post::class)->make();
        assert($post3 instanceof Post);
        $post3->parent()->associate($post2);
        $post3->save();

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

    public function testQueryHasManyPaginatorBeforeQuery(): void
    {
        // BeforeQuery
        $this->schema = /** @lang GraphQL */ '
        type User {
            id: Int!
            tasks: [Task!]! @hasMany(type: PAGINATOR)
        }

        type Task {
            id: Int!
        }

        type Query {
            user(id: ID! @eq): User @find
            tasks: [Task!]! @paginate
        }
        ';

        $user = factory(User::class)->create();
        assert($user instanceof User);

        $tasks = factory(Task::class, 3)->make();
        $user->tasks()->saveMany($tasks);

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

    public function testQueryHasManyPaginatorAfterQuery(): void
    {
        // AfterQuery
        $this->schema = /** @lang GraphQL */ '
        type Query {
            user(id: ID! @eq): User @find
            tasks: [Task!]! @paginate
        }

        type User {
            id: Int!
            tasks: [Task!]! @hasMany(type: PAGINATOR)
        }

        type Task {
            id: Int!
        }
        ';

        $user = factory(User::class)->create();
        assert($user instanceof User);

        $tasks = factory(Task::class, 3)->make();
        $user->tasks()->saveMany($tasks);

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

    public function testQueryHasManyNoTypePaginator(): void
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

        $user = factory(User::class)->create();
        assert($user instanceof User);

        $tasks = factory(Task::class, 3)->make();
        $user->tasks()->saveMany($tasks);

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

    public function testHasManyWithModelAndPaginatedRelation(): void
    {
        $this->schema = /** @lang GraphQL */ '
        type User {
            tasks: [Task!]! @hasMany(type: PAGINATOR) @can(ability: "adminOnly")
        }

        type Task @model {
            id: Int
        }

        type Query {
            user: User @first
        }
        ';

        $user = factory(User::class)->make();
        assert($user instanceof User);
        $user->name = UserPolicy::ADMIN;
        $user->save();

        $this->be($user);

        $tasks = factory(Task::class, 3)->make();
        $user->tasks()->saveMany($tasks);

        $this->graphQL(/** @lang GraphQL */ '
        {
            user {
                tasks(first: 3) {
                    data {
                        id
                    }
                }
            }
        }
        ')->assertJsonCount(3, 'data.user.tasks.data');
    }

    public function testHasManyWithRenamedModelAndPaginatedRelation(): void
    {
        $this->schema = /** @lang GraphQL */ '
        type User {
            foos: [Foo!]! @hasMany(type: PAGINATOR, relation: "tasks") @can(ability: "adminOnly")
        }

        type Foo @model(class: "Task") {
            id: Int
        }

        type Query {
            user: User @first
        }
        ';

        $user = factory(User::class)->make();
        assert($user instanceof User);
        $user->name = UserPolicy::ADMIN;
        $user->save();

        $this->be($user);

        $tasks = factory(Task::class, 3)->make();
        $user->tasks()->saveMany($tasks);

        $this->graphQL(/** @lang GraphQL */ '
        {
            user {
                foos(first: 3) {
                    data {
                        id
                    }
                }
            }
        }
        ')->assertJsonCount(3, 'data.user.foos.data');
    }

    public function testHasManyWithRenamedModelAndConnection(): void
    {
        $this->schema = /** @lang GraphQL */ '
        type User {
            foos: [Foo!]! @hasMany(type: CONNECTION, relation: "tasks") @can(ability: "adminOnly")
        }

        type Foo @model(class: "Task") {
            id: Int
        }

        type Query {
            user: User @first
        }
        ';

        $user = factory(User::class)->make();
        assert($user instanceof User);
        $user->name = UserPolicy::ADMIN;
        $user->save();

        $this->be($user);

        $tasks = factory(Task::class, 3)->make();
        $user->tasks()->saveMany($tasks);

        $this->graphQL(/** @lang GraphQL */ '
        {
            user {
                foos(first: 3) {
                    edges {
                        node {
                            id
                        }
                    }
                }
            }
        }
        ')->assertJsonCount(3, 'data.user.foos.edges');
    }

    /** @return iterable<array{bool}> */
    public static function batchloadRelations(): iterable
    {
        yield [true];
        yield [false];
    }
}
