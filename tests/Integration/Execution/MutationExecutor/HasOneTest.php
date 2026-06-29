<?php declare(strict_types=1);

namespace Tests\Integration\Execution\MutationExecutor;

use PHPUnit\Framework\Attributes\DataProvider;
use Tests\DBTestCase;
use Tests\Utils\Models\Post;
use Tests\Utils\Models\Task;

final class HasOneTest extends DBTestCase
{
    protected string $schema = /** @lang GraphQL */ <<<'GRAPHQL'
    type Task {
        id: ID!
        name: String!
        post: Post @hasOne
    }

    type Post {
        id: ID!
        title: String!
        body: String!
    }

    type Mutation {
        createTask(input: CreateTaskInput! @spread): Task @create
        updateTask(input: UpdateTaskInput! @spread): Task @update
        upsertTask(input: UpsertTaskInput! @spread): Task @upsert
    }

    input CreateTaskInput {
        name: String!
        post: CreatePostRelation
    }

    input CreatePostRelation {
        create: CreatePostInput
        upsert: UpsertPostInput
    }

    input CreatePostInput {
        title: String!
    }

    input UpdateTaskInput {
        id: ID!
        name: String
        post: UpdatePostHasOne
    }

    input UpdatePostHasOne {
        create: CreatePostInput
        update: UpdatePostInput
        upsert: UpsertPostInput
        delete: ID
    }

    input UpdatePostInput {
        id: ID!
        title: String
    }

    input UpsertTaskInput {
        id: ID
        name: String
        post: UpsertPostHasOne
    }

    input UpsertPostHasOne {
        create: CreatePostInput
        update: UpdatePostInput
        upsert: UpsertPostInput
        delete: ID
    }

    input UpsertPostInput {
        id: ID
        title: String
    }
    GRAPHQL . self::PLACEHOLDER_QUERY;

    public function testCreateWithNewHasOne(): void
    {
        $this->graphQL(/** @lang GraphQL */ <<<'GRAPHQL'
        mutation {
            createTask(input: {
                name: "foo"
                post: {
                    create: {
                        title: "bar"
                    }
                }
            }) {
                id
                name
                post {
                    id
                    title
                }
            }
        }
        GRAPHQL)->assertJson([
            'data' => [
                'createTask' => [
                    'id' => '1',
                    'name' => 'foo',
                    'post' => [
                        'id' => '1',
                        'title' => 'bar',
                    ],
                ],
            ],
        ]);
    }

    public function testUpsertWithNewHasOne(): void
    {
        $this->graphQL(/** @lang GraphQL */ <<<'GRAPHQL'
        mutation {
            createTask(input: {
                name: "foo"
                post: {
                    upsert: {
                        id: 2
                        title: "bar"
                    }
                }
            }) {
                id
                name
                post {
                    id
                    title
                }
            }
        }
        GRAPHQL)->assertJson([
            'data' => [
                'createTask' => [
                    'id' => '1',
                    'name' => 'foo',
                    'post' => [
                        'id' => '2',
                        'title' => 'bar',
                    ],
                ],
            ],
        ]);
    }

    public function testCreateUsingUpsertWithNewHasOne(): void
    {
        $this->graphQL(/** @lang GraphQL */ <<<'GRAPHQL'
        mutation {
            upsertTask(input: {
                id: 2
                name: "foo"
                post: {
                    upsert: {
                        id: 3
                        title: "bar"
                    }
                }
            }) {
                id
                name
                post {
                    id
                    title
                }
            }
        }
        GRAPHQL)->assertJson([
            'data' => [
                'upsertTask' => [
                    'id' => '2',
                    'name' => 'foo',
                    'post' => [
                        'id' => '3',
                        'title' => 'bar',
                    ],
                ],
            ],
        ]);
    }

    public function testUpsertHasOneWithoutID(): void
    {
        $this->graphQL(/** @lang GraphQL */ <<<'GRAPHQL'
        mutation {
            upsertTask(input: {
                name: "foo"
                post: {
                    upsert: {
                        title: "bar"
                    }
                }
            }) {
                id
                name
                post {
                    id
                    title
                }
            }
        }
        GRAPHQL)->assertJson([
            'data' => [
                'upsertTask' => [
                    'id' => '1',
                    'name' => 'foo',
                    'post' => [
                        'id' => '1',
                        'title' => 'bar',
                    ],
                ],
            ],
        ]);
    }

    public function testUpsertHasOneWithIDNull(): void
    {
        $this->graphQL(/** @lang GraphQL */ <<<'GRAPHQL'
        mutation {
            upsertTask(input: {
                name: "foo"
                post: {
                    upsert: {
                        id: null
                        title: "bar"
                    }
                }
            }) {
                id
                name
                post {
                    id
                    title
                }
            }
        }
        GRAPHQL)->assertJson([
            'data' => [
                'upsertTask' => [
                    'id' => '1',
                    'name' => 'foo',
                    'post' => [
                        'id' => '1',
                        'title' => 'bar',
                    ],
                ],
            ],
        ]);
    }

    public function testAllowsNullOperations(): void
    {
        factory(Task::class)->create();

        $this->graphQL(/** @lang GraphQL */ <<<'GRAPHQL'
        mutation {
            updateTask(input: {
                id: 1
                name: "foo"
                post: {
                    create: null
                    update: null
                    upsert: null
                    delete: null
                }
            }) {
                name
                post {
                    id
                }
            }
        }
        GRAPHQL)->assertJson([
            'data' => [
                'updateTask' => [
                    'name' => 'foo',
                    'post' => null,
                ],
            ],
        ]);
    }

    /** @return iterable<array{string}> */
    public static function existingModelMutations(): iterable
    {
        yield 'Update action' => ['update'];
        yield 'Upsert action' => ['upsert'];
    }

    /** @dataProvider existingModelMutations */
    #[DataProvider('existingModelMutations')]
    public function testUpdateWithNewHasOne(string $action): void
    {
        factory(Task::class)->create();

        $this->graphQL(/** @lang GraphQL */ <<<GRAPHQL
        mutation {
            {$action}Task(input: {
                id: 1
                name: "foo"
                post: {
                    create: {
                        title: "bar"
                    }
                }
            }) {
                id
                name
                post {
                    id
                    title
                }
            }
        }
        GRAPHQL)->assertJson([
            'data' => [
                "{$action}Task" => [
                    'id' => '1',
                    'name' => 'foo',
                    'post' => [
                        'id' => '1',
                        'title' => 'bar',
                    ],
                ],
            ],
        ]);
    }

    /** @dataProvider existingModelMutations */
    #[DataProvider('existingModelMutations')]
    public function testCreateHasOneWhenAlreadyExists(string $action): void
    {
        $task = factory(Task::class)->create();
        $this->assertInstanceOf(Task::class, $task);

        $task->post()
            ->save(
                factory(Post::class)->create(),
            );

        $this->graphQL(/** @lang GraphQL */ <<<GRAPHQL
        mutation {
            {$action}Task(input: {
                id: 1
                name: "foo"
                post: {
                    create: {
                        title: "bar"
                    }
                }
            }) {
                id
                name
                post {
                    id
                    title
                }
            }
        }
        GRAPHQL)->assertGraphQLErrorMessage('Cannot create a related model: a Post already exists for this Task. Use upsert to modify the existing model.');

        $this->assertSame(1, Post::count());
    }

    /** @dataProvider existingModelMutations */
    #[DataProvider('existingModelMutations')]
    public function testUpdateAndUpdateHasOne(string $action): void
    {
        $task = factory(Task::class)->create();
        $this->assertInstanceOf(Task::class, $task);

        $task->post()
            ->save(
                factory(Post::class)->create(),
            );

        $this->graphQL(/** @lang GraphQL */ <<<GRAPHQL
        mutation {
            {$action}Task(input: {
                id: 1
                name: "foo"
                post: {
                    update: {
                        id: 1
                        title: "bar"
                    }
                }
            }) {
                id
                name
                post {
                    id
                    title
                }
            }
        }
        GRAPHQL)->assertJson([
            'data' => [
                "{$action}Task" => [
                    'id' => '1',
                    'name' => 'foo',
                    'post' => [
                        'id' => '1',
                        'title' => 'bar',
                    ],
                ],
            ],
        ]);
    }

    /** @dataProvider existingModelMutations */
    #[DataProvider('existingModelMutations')]
    public function testUpdateAndUpsertHasOne(string $action): void
    {
        $task = factory(Task::class)->create();
        $this->assertInstanceOf(Task::class, $task);

        $task->post()
            ->save(
                factory(Post::class)->create(),
            );

        $this->graphQL(/** @lang GraphQL */ <<<GRAPHQL
        mutation {
            {$action}Task(input: {
                id: 1
                name: "foo"
                post: {
                    upsert: {
                        id: 1
                        title: "bar"
                    }
                }
            }) {
                id
                name
                post {
                    id
                    title
                }
            }
        }
        GRAPHQL)->assertJson([
            'data' => [
                "{$action}Task" => [
                    'id' => '1',
                    'name' => 'foo',
                    'post' => [
                        'id' => '1',
                        'title' => 'bar',
                    ],
                ],
            ],
        ]);
    }

    /** @dataProvider existingModelMutations */
    #[DataProvider('existingModelMutations')]
    public function testUpdateAndUpsertExistingHasOneWithoutID(string $action): void
    {
        $task = factory(Task::class)->create();
        $this->assertInstanceOf(Task::class, $task);

        $task->post()
            ->save(
                factory(Post::class)->create(),
            );

        $this->graphQL(/** @lang GraphQL */ <<<GRAPHQL
        mutation {
            {$action}Task(input: {
                id: 1
                name: "foo"
                post: {
                    upsert: {
                        title: "bar"
                    }
                }
            }) {
                id
                name
                post {
                    id
                    title
                }
            }
        }
        GRAPHQL)->assertJson([
            'data' => [
                "{$action}Task" => [
                    'id' => '1',
                    'name' => 'foo',
                    'post' => [
                        'id' => '1',
                        'title' => 'bar',
                    ],
                ],
            ],
        ]);

        // Assert that the existing post was updated, not a new one created
        $this->assertSame(1, Post::count());
    }

    /** @dataProvider existingModelMutations */
    #[DataProvider('existingModelMutations')]
    public function testUpdateAndDeleteHasOne(string $action): void
    {
        $task = factory(Task::class)->create();
        $this->assertInstanceOf(Task::class, $task);

        $task->post()
            ->save(
                factory(Post::class)->create(),
            );

        $this->graphQL(/** @lang GraphQL */ <<<GRAPHQL
        mutation {
            {$action}Task(input: {
                id: 1
                name: "foo"
                post: {
                    delete: 1
                }
            }) {
                id
                name
                post {
                    id
                    title
                }
            }
        }
        GRAPHQL)->assertJson([
            'data' => [
                "{$action}Task" => [
                    'id' => '1',
                    'name' => 'foo',
                    'post' => null,
                ],
            ],
        ]);
    }

    public function testCreateWithNewHasOneOnInputField(): void
    {
        $this->schema = /** @lang GraphQL */ <<<'GRAPHQL'
        type Task {
            id: ID!
            name: String!
            post: Post @hasOne
        }

        type Post {
            id: ID!
            title: String!
            body: String!
        }

        type Mutation {
            createTask(input: CreateTaskInput! @spread): Task @create
        }

        input CreateTaskInput {
            name: String!
            post: CreatePostRelation @hasOne
        }

        input CreatePostRelation {
            create: CreatePostInput
        }

        input CreatePostInput {
            title: String!
        }
        GRAPHQL . self::PLACEHOLDER_QUERY;

        $this->graphQL(/** @lang GraphQL */ <<<'GRAPHQL'
        mutation {
            createTask(input: {
                name: "foo"
                post: {
                    create: {
                        title: "bar"
                    }
                }
            }) {
                id
                name
                post {
                    id
                    title
                }
            }
        }
        GRAPHQL)->assertJson([
            'data' => [
                'createTask' => [
                    'id' => '1',
                    'name' => 'foo',
                    'post' => [
                        'id' => '1',
                        'title' => 'bar',
                    ],
                ],
            ],
        ]);
    }

    public function testUpdateWithCreateHasOneOnInputField(): void
    {
        $this->schema = /** @lang GraphQL */ <<<'GRAPHQL'
        type Task {
            id: ID!
            name: String!
            post: Post @hasOne
        }

        type Post {
            id: ID!
            title: String!
            body: String!
        }

        type Mutation {
            updateTask(input: UpdateTaskInput! @spread): Task @update
        }

        input UpdateTaskInput {
            id: ID!
            name: String
            post: UpdatePostRelation @hasOne
        }

        input UpdatePostRelation {
            create: CreatePostInput
            update: UpdatePostInput
            delete: ID
        }

        input CreatePostInput {
            title: String!
        }

        input UpdatePostInput {
            id: ID!
            title: String
        }
        GRAPHQL . self::PLACEHOLDER_QUERY;

        factory(Task::class)->create();

        $this->graphQL(/** @lang GraphQL */ <<<'GRAPHQL'
        mutation {
            updateTask(input: {
                id: 1
                name: "updated"
                post: {
                    create: {
                        title: "new post"
                    }
                }
            }) {
                id
                name
                post {
                    id
                    title
                }
            }
        }
        GRAPHQL)->assertJson([
            'data' => [
                'updateTask' => [
                    'id' => '1',
                    'name' => 'updated',
                    'post' => [
                        'id' => '1',
                        'title' => 'new post',
                    ],
                ],
            ],
        ]);
    }

    public function testUpdateWithUpdateHasOneOnInputField(): void
    {
        $this->schema = /** @lang GraphQL */ <<<'GRAPHQL'
        type Task {
            id: ID!
            name: String!
            post: Post @hasOne
        }

        type Post {
            id: ID!
            title: String!
            body: String!
        }

        type Mutation {
            updateTask(input: UpdateTaskInput! @spread): Task @update
        }

        input UpdateTaskInput {
            id: ID!
            name: String
            post: UpdatePostRelation @hasOne
        }

        input UpdatePostRelation {
            create: CreatePostInput
            update: UpdatePostInput
            delete: ID
        }

        input CreatePostInput {
            title: String!
        }

        input UpdatePostInput {
            id: ID!
            title: String
        }
        GRAPHQL . self::PLACEHOLDER_QUERY;

        $task = factory(Task::class)->create();
        $post = factory(Post::class)->make();
        $post->title = 'original';
        $post->task()->associate($task);
        $post->save();

        $this->graphQL(/** @lang GraphQL */ <<<'GRAPHQL'
        mutation {
            updateTask(input: {
                id: 1
                post: {
                    update: {
                        id: 1
                        title: "changed"
                    }
                }
            }) {
                id
                post {
                    id
                    title
                }
            }
        }
        GRAPHQL)->assertJson([
            'data' => [
                'updateTask' => [
                    'id' => '1',
                    'post' => [
                        'id' => '1',
                        'title' => 'changed',
                    ],
                ],
            ],
        ]);
    }

    public function testUpdateWithDeleteHasOneOnInputField(): void
    {
        $this->schema = /** @lang GraphQL */ <<<'GRAPHQL'
        type Task {
            id: ID!
            name: String!
            post: Post @hasOne
        }

        type Post {
            id: ID!
            title: String!
            body: String!
        }

        type Mutation {
            updateTask(input: UpdateTaskInput! @spread): Task @update
        }

        input UpdateTaskInput {
            id: ID!
            name: String
            post: UpdatePostRelation @hasOne
        }

        input UpdatePostRelation {
            create: CreatePostInput
            update: UpdatePostInput
            delete: ID
        }

        input CreatePostInput {
            title: String!
        }

        input UpdatePostInput {
            id: ID!
            title: String
        }
        GRAPHQL . self::PLACEHOLDER_QUERY;

        $task = factory(Task::class)->create();
        $post = factory(Post::class)->make();
        $post->task()->associate($task);
        $post->save();

        $this->graphQL(/** @lang GraphQL */ <<<GRAPHQL
        mutation {
            updateTask(input: {
                id: 1
                post: {
                    delete: {$post->id}
                }
            }) {
                id
                post {
                    id
                }
            }
        }
        GRAPHQL)->assertJson([
            'data' => [
                'updateTask' => [
                    'id' => '1',
                    'post' => null,
                ],
            ],
        ]);

        $this->assertNull(Post::find($post->id));
    }
}
