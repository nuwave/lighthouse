<?php declare(strict_types=1);

namespace Tests\Integration\Schema\Directives;

use Carbon\Carbon;
use Tests\DBTestCase;
use Tests\Utils\Models\Image;
use Tests\Utils\Models\Post;
use Tests\Utils\Models\Task;
use Tests\Utils\Models\User;

final class MorphToDirectiveTest extends DBTestCase
{
    public function testResolveMorphToRelationship(): void
    {
        $user = factory(User::class)->create();
        $this->assertInstanceOf(User::class, $user);

        $task = factory(Task::class)->make();
        $this->assertInstanceOf(Task::class, $task);
        $task->user()->associate($user);
        $task->save();

        $image = factory(Image::class)->make();
        $this->assertInstanceOf(Image::class, $image);
        $image->imageable()->associate($task);
        $image->save();

        $this->schema = /** @lang GraphQL */ <<<'GRAPHQL'
        type Image {
            id: ID!
            imageable: Task! @morphTo
        }

        type Task {
            id: ID!
            name: String!
        }

        type Query {
            image (
                id: ID! @eq
            ): Image @find
        }
        GRAPHQL;

        $this->graphQL(/** @lang GraphQL */ <<<'GRAPHQL'
        query ($id: ID!) {
            image(id: $id) {
                id
                imageable {
                    id
                    name
                }
            }
        }
        GRAPHQL, [
            'id' => $image->id,
        ])->assertJson([
            'data' => [
                'image' => [
                    'id' => $image->id,
                    'imageable' => [
                        'id' => $task->id,
                        'name' => $task->name,
                    ],
                ],
            ],
        ]);
    }

    public function testResolveMorphToWithCustomName(): void
    {
        $user = factory(User::class)->create();
        $this->assertInstanceOf(User::class, $user);

        $task = factory(Task::class)->make();
        $this->assertInstanceOf(Task::class, $task);
        $task->user()->associate($user);
        $task->save();

        $image = factory(Image::class)->make();
        $this->assertInstanceOf(Image::class, $image);
        $image->imageable()->associate($task);
        $image->save();

        $this->schema = /** @lang GraphQL */ <<<'GRAPHQL'
        type Image {
            id: ID!
            customImageable: Task! @morphTo(relation: "imageable")
        }

        type Task {
            id: ID!
            name: String!
        }

        type Query {
            image (
                id: ID! @eq
            ): Image @find
        }
        GRAPHQL;

        $this->graphQL(/** @lang GraphQL */ <<<'GRAPHQL'
        query ($id: ID!) {
            image(id: $id) {
                id
                customImageable {
                    id
                    name
                }
            }
        }
        GRAPHQL, [
            'id' => $image->id,
        ])->assertJson([
            'data' => [
                'image' => [
                    'id' => $image->id,
                    'customImageable' => [
                        'id' => $task->id,
                        'name' => $task->name,
                    ],
                ],
            ],
        ]);
    }

    public function testResolveMorphToWithScopes(): void
    {
        $user = factory(User::class)->create();
        assert($user instanceof User);

        $task = factory(Task::class)->make();
        assert($task instanceof Task);
        $task->user()->associate($user);
        $task->save();

        $image = factory(Image::class)->make();
        assert($image instanceof Image);
        $image->imageable()->associate($task);
        $image->save();

        $post = factory(Post::class)->make();
        assert($post instanceof Post);
        $post->user()->associate($user->id);
        $post->save();

        $postImage = factory(Image::class)->make();
        assert($postImage instanceof Image);
        $postImage->imageable()->associate($post);
        $postImage->save();

        $this->schema = /** @lang GraphQL */ '
        interface Imageable {
            id: ID!
        }

        type Task implements Imageable {
            id: ID!
            name: String!
        }

        type Post implements Imageable {
            id: ID!
            title: String!
        }

        type Image {
            id: ID!
            imageable: Imageable @morphTo(scopes: [
                { model: "Task", scopes: ["completed"] }
            ])
        }

        type Query {
            image (
                id: ID! @eq
            ): Image @find
        }
        ';

        $this->graphQL(/** @lang GraphQL */ '
        query ($taskImage: ID!, $postImage: ID!){
            taskImage: image(id: $taskImage) {
                id
                imageable {
                    ... on Task {
                        id
                        name
                    }
                    ... on Post {
                        id
                        title
                    }
                }
            }
            postImage: image(id: $postImage) {
                id
                imageable {
                    ... on Task {
                        id
                        name
                    }
                    ... on Post {
                        id
                        title
                    }
                }
            }
        }
        ', [
            'taskImage' => $image->id,
            'postImage' => $postImage->id,
        ])->assertJson([
            'data' => [
                'taskImage' => [
                    'id' => $image->id,
                    'imageable' => null,
                ],
                'postImage' => [
                    'id' => $postImage->id,
                    'imageable' => [
                        'id' => $post->id,
                        'title' => $post->title,
                    ],
                ],
            ],
        ]);

        $task->completed_at = Carbon::now();
        $task->save();

        $this->graphQL(/** @lang GraphQL */ '
        query ($taskImage: ID!, $postImage: ID!){
            taskImage: image(id: $taskImage) {
                id
                imageable {
                    ... on Task {
                        id
                        name
                    }
                    ... on Post {
                        id
                        title
                    }
                }
            }
            postImage: image(id: $postImage) {
                id
                imageable {
                    ... on Task {
                        id
                        name
                    }
                    ... on Post {
                        id
                        title
                    }
                }
            }
        }
        ', [
            'taskImage' => $image->id,
            'postImage' => $postImage->id,
        ])->assertJson([
            'data' => [
                'taskImage' => [
                    'id' => $image->id,
                    'imageable' => [
                        'id' => $task->id,
                        'name' => $task->name,
                    ],
                ],
                'postImage' => [
                    'id' => $postImage->id,
                    'imageable' => [
                        'id' => $post->id,
                        'title' => $post->title,
                    ],
                ],
            ],
        ]);
    }

    public function testResolveMorphToUsingInterfaces(): void
    {
        $user = factory(User::class)->create();
        $this->assertInstanceOf(User::class, $user);

        $task = factory(Task::class)->make();
        $this->assertInstanceOf(Task::class, $task);
        $task->user()->associate($user);
        $task->save();

        $image = factory(Image::class)->make();
        $this->assertInstanceOf(Image::class, $image);
        $image->imageable()->associate($task);
        $image->save();

        $post = factory(Post::class)->make();
        $this->assertInstanceOf(Post::class, $post);
        $post->user()->associate($user);
        $post->save();

        $postImage = factory(Image::class)->make();
        $this->assertInstanceOf(Image::class, $postImage);
        $postImage->imageable()->associate($post);
        $postImage->save();

        $this->schema = /** @lang GraphQL */ <<<'GRAPHQL'
        interface Imageable {
            id: ID!
        }

        type Task implements Imageable {
            id: ID!
            name: String!
        }

        type Post implements Imageable {
            id: ID!
            title: String!
        }

        type Image {
            id: ID!
            imageable: Imageable! @morphTo
        }

        type Query {
            image (
                id: ID! @eq
            ): Image @find
        }
        GRAPHQL;

        $this->graphQL(/** @lang GraphQL */ <<<'GRAPHQL'
        query ($taskImage: ID!, $postImage: ID!) {
            taskImage: image(id: $taskImage) {
                id
                imageable {
                    ... on Task {
                        id
                        name
                    }
                    ... on Post {
                        id
                        title
                    }
                }
            }
            postImage: image(id: $postImage) {
                id
                imageable {
                    ... on Task {
                        id
                        name
                    }
                    ... on Post {
                        id
                        title
                    }
                }
            }
        }
        GRAPHQL, [
            'taskImage' => $image->id,
            'postImage' => $postImage->id,
        ])->assertJson([
            'data' => [
                'taskImage' => [
                    'id' => $image->id,
                    'imageable' => [
                        'id' => $task->id,
                        'name' => $task->name,
                    ],
                ],
                'postImage' => [
                    'id' => $postImage->id,
                    'imageable' => [
                        'id' => $post->id,
                        'title' => $post->title,
                    ],
                ],
            ],
        ]);
    }

    public function testResolveMorphToUsingInterfacesWithShortcutForeignKeySelectionSelectID(): void
    {
        config(['lighthouse.shortcut_foreign_key_selection' => true]);

        $user = factory(User::class)->create();
        $this->assertInstanceOf(User::class, $user);

        $task = factory(Task::class)->make();
        $this->assertInstanceOf(Task::class, $task);
        $task->user()->associate($user);
        $task->save();

        $image = factory(Image::class)->make();
        $this->assertInstanceOf(Image::class, $image);
        $image->imageable()->associate($task);
        $image->save();

        $post = factory(Post::class)->make();
        $this->assertInstanceOf(Post::class, $post);
        $post->user()->associate($user);
        $post->save();

        $postImage = factory(Image::class)->make();
        $this->assertInstanceOf(Image::class, $postImage);
        $postImage->imageable()->associate($post);
        $postImage->save();

        $this->schema = /** @lang GraphQL */ <<<'GRAPHQL'
        interface Imageable {
            id: ID!
        }

        type Task implements Imageable {
            id: ID!
            name: String!
        }

        type Post implements Imageable {
            id: ID!
            title: String!
        }

        type Image {
            id: ID!
            imageable: Imageable! @morphTo
        }

        type Query {
            image (
                id: ID! @eq
            ): Image @find
        }
        GRAPHQL;

        $this->assertQueryCountMatches(2, function () use ($image, $postImage, $task, $post): void {
            $this->graphQL(/** @lang GraphQL */ <<<'GRAPHQL'
                query ($taskImage: ID!, $postImage: ID!) {
                    taskImage: image(id: $taskImage) {
                        id
                        imageable {
                            ... on Task {
                                id
                            }
                            ... on Post {
                                id
                            }
                        }
                    }
                    postImage: image(id: $postImage) {
                        id
                        imageable {
                            ... on Task {
                                id
                            }
                            ... on Post {
                                id
                            }
                        }
                    }
                }
            GRAPHQL, [
                'taskImage' => $image->id,
                'postImage' => $postImage->id,
            ])->assertJson([
                'data' => [
                    'taskImage' => [
                        'id' => $image->id,
                        'imageable' => [
                            'id' => $task->id,
                        ],
                    ],
                    'postImage' => [
                        'id' => $postImage->id,
                        'imageable' => [
                            'id' => $post->id,
                        ],
                    ],
                ],
            ]);
        });
    }

    public function testResolveMorphToUsingInterfacesWithShortcutForeignKeySelectionSelectIDAndTypename(): void
    {
        config(['lighthouse.shortcut_foreign_key_selection' => true]);

        $user = factory(User::class)->create();
        $this->assertInstanceOf(User::class, $user);

        $task = factory(Task::class)->make();
        $this->assertInstanceOf(Task::class, $task);
        $task->user()->associate($user);
        $task->save();

        $image = factory(Image::class)->make();
        $this->assertInstanceOf(Image::class, $image);
        $image->imageable()->associate($task);
        $image->save();

        $post = factory(Post::class)->make();
        $this->assertInstanceOf(Post::class, $post);
        $post->user()->associate($user);
        $post->save();

        $postImage = factory(Image::class)->make();
        $this->assertInstanceOf(Image::class, $postImage);
        $postImage->imageable()->associate($post);
        $postImage->save();

        $this->schema = /** @lang GraphQL */ <<<'GRAPHQL'
        interface Imageable {
            id: ID!
        }

        type Task implements Imageable {
            id: ID!
            name: String!
        }

        type Post implements Imageable {
            id: ID!
            title: String!
        }

        type Image {
            id: ID!
            imageable: Imageable! @morphTo
        }

        type Query {
            image (
                id: ID! @eq
            ): Image @find
        }
        GRAPHQL;

        $this->assertQueryCountMatches(2, function () use ($image, $postImage, $task, $post): void {
            $this->graphQL(/** @lang GraphQL */ <<<'GRAPHQL'
                query ($taskImage: ID!, $postImage: ID!) {
                    taskImage: image(id: $taskImage) {
                        id
                        imageable {
                            __typename
                            ... on Task {
                                id
                            }
                            ... on Post {
                                id
                            }
                        }
                    }
                    postImage: image(id: $postImage) {
                        id
                        imageable {
                            __typename
                            ... on Task {
                                id
                            }
                            ... on Post {
                                id
                            }
                        }
                    }
                }
            GRAPHQL, [
                'taskImage' => $image->id,
                'postImage' => $postImage->id,
            ])->assertJson([
                'data' => [
                    'taskImage' => [
                        'id' => $image->id,
                        'imageable' => [
                            '__typename' => 'Task',
                            'id' => $task->id,
                        ],
                    ],
                    'postImage' => [
                        'id' => $postImage->id,
                        'imageable' => [
                            '__typename' => 'Post',
                            'id' => $post->id,
                        ],
                    ],
                ],
            ]);
        });
    }

    public function testResolveMorphToUsingUnions(): void
    {
        $user = factory(User::class)->create();
        $this->assertInstanceOf(User::class, $user);

        $task = factory(Task::class)->make();
        $this->assertInstanceOf(Task::class, $task);
        $task->user()->associate($user);
        $task->save();

        $image = factory(Image::class)->make();
        $this->assertInstanceOf(Image::class, $image);
        $image->imageable()->associate($task);
        $image->save();

        $post = factory(Post::class)->make();
        $this->assertInstanceOf(Post::class, $post);
        $post->user()->associate($user);
        $post->save();

        $postImage = factory(Image::class)->make();
        $this->assertInstanceOf(Image::class, $postImage);
        $postImage->imageable()->associate($post);
        $postImage->save();

        $this->schema = /** @lang GraphQL */ <<<'GRAPHQL'
        union Imageable = Task | Post

        type Task {
            id: ID!
            name: String!
        }

        type Post {
            id: ID!
            title: String!
        }

        type Image {
            id: ID!
            imageable: Imageable! @morphTo
        }

        type Query {
            image (
                id: ID! @eq
            ): Image @find
        }
        GRAPHQL;

        $this->graphQL(/** @lang GraphQL */ <<<'GRAPHQL'
        query ($taskImage: ID!, $postImage: ID!) {
            taskImage: image(id: $taskImage) {
                id
                imageable {
                    __typename
                    ... on Task {
                        id
                        name
                    }
                    ... on Post {
                        id
                        title
                    }
                }
            }
            postImage: image(id: $postImage) {
                id
                imageable {
                    __typename
                    ... on Task {
                        id
                        name
                    }
                    ... on Post {
                        id
                        title
                    }
                }
            }
        }
        GRAPHQL, [
            'taskImage' => $image->id,
            'postImage' => $postImage->id,
        ])->assertJson([
            'data' => [
                'taskImage' => [
                    'id' => $image->id,
                    'imageable' => [
                        '__typename' => 'Task',
                        'id' => $task->id,
                        'name' => $task->name,
                    ],
                ],
                'postImage' => [
                    'id' => $postImage->id,
                    'imageable' => [
                        '__typename' => 'Post',
                        'id' => $post->id,
                        'title' => $post->title,
                    ],
                ],
            ],
        ]);
    }

    public function testShortcutsForeignKeySelectionUsingUnionSelectID(): void
    {
        config(['lighthouse.shortcut_foreign_key_selection' => true]);

        $user = factory(User::class)->create();
        $this->assertInstanceOf(User::class, $user);

        $task = factory(Task::class)->make();
        $this->assertInstanceOf(Task::class, $task);
        $task->user()->associate($user);
        $task->save();

        $image = factory(Image::class)->make();
        $this->assertInstanceOf(Image::class, $image);
        $image->imageable()->associate($task);
        $image->save();

        $post = factory(Post::class)->make();
        $this->assertInstanceOf(Post::class, $post);
        $post->user()->associate($user);
        $post->save();

        $postImage = factory(Image::class)->make();
        $this->assertInstanceOf(Image::class, $postImage);
        $postImage->imageable()->associate($post);
        $postImage->save();

        $this->schema = /** @lang GraphQL */ <<<'GRAPHQL'
        union Imageable = Task | Post

        type Task {
            id: ID!
            name: String!
        }

        type Post {
            id: ID!
            title: String!
        }

        type Image {
            id: ID!
            imageable: Imageable! @morphTo
        }

        type Query {
            image (
                id: ID! @eq
            ): Image @find
        }
        GRAPHQL;

        $this->assertQueryCountMatches(2, function () use ($image, $postImage, $task, $post): void {
            $this->graphQL(/** @lang GraphQL */ <<<'GRAPHQL'
                query ($taskImage: ID!, $postImage: ID!) {
                    taskImage: image(id: $taskImage) {
                        id
                        imageable {
                            ... on Task {
                                id
                            }
                            ... on Post {
                                id
                            }
                        }
                    }
                    postImage: image(id: $postImage) {
                        id
                        imageable {
                            ... on Task {
                                id
                            }
                            ... on Post {
                                id
                            }
                        }
                    }
                }
            GRAPHQL, [
                'taskImage' => $image->id,
                'postImage' => $postImage->id,
            ])->assertJson([
                'data' => [
                    'taskImage' => [
                        'id' => $image->id,
                        'imageable' => [
                            'id' => $task->id,
                        ],
                    ],
                    'postImage' => [
                        'id' => $postImage->id,
                        'imageable' => [
                            'id' => $post->id,
                        ],
                    ],
                ],
            ]);
        });
    }

    public function testShortcutsForeignKeySelectionUsingUnionSelectIDAndTypename(): void
    {
        config(['lighthouse.shortcut_foreign_key_selection' => true]);

        $user = factory(User::class)->create();
        $this->assertInstanceOf(User::class, $user);

        $task = factory(Task::class)->make();
        $this->assertInstanceOf(Task::class, $task);
        $task->user()->associate($user);
        $task->save();

        $image = factory(Image::class)->make();
        $this->assertInstanceOf(Image::class, $image);
        $image->imageable()->associate($task);
        $image->save();

        $post = factory(Post::class)->make();
        $this->assertInstanceOf(Post::class, $post);
        $post->user()->associate($user);
        $post->save();

        $postImage = factory(Image::class)->make();
        $this->assertInstanceOf(Image::class, $postImage);
        $postImage->imageable()->associate($post);
        $postImage->save();

        $this->schema = /** @lang GraphQL */ <<<'GRAPHQL'
        union Imageable = Task | Post

        type Task {
            id: ID!
            name: String!
        }

        type Post {
            id: ID!
            title: String!
        }

        type Image {
            id: ID!
            imageable: Imageable! @morphTo
        }

        type Query {
            image (
                id: ID! @eq
            ): Image @find
        }
        GRAPHQL;

        $this->assertQueryCountMatches(2, function () use ($image, $postImage, $task, $post): void {
            $this->graphQL(/** @lang GraphQL */ <<<'GRAPHQL'
                query ($taskImage: ID!, $postImage: ID!) {
                    taskImage: image(id: $taskImage) {
                        id
                        imageable {
                            __typename
                            ... on Task {
                                id
                            }
                            ... on Post {
                                id
                            }
                        }
                    }
                    postImage: image(id: $postImage) {
                        id
                        imageable {
                            __typename
                            ... on Task {
                                id
                            }
                            ... on Post {
                                id
                            }
                        }
                    }
                }
            GRAPHQL, [
                'taskImage' => $image->id,
                'postImage' => $postImage->id,
            ])->assertJson([
                'data' => [
                    'taskImage' => [
                        'id' => $image->id,
                        'imageable' => [
                            '__typename' => 'Task',
                            'id' => $task->id,
                        ],
                    ],
                    'postImage' => [
                        'id' => $postImage->id,
                        'imageable' => [
                            '__typename' => 'Post',
                            'id' => $post->id,
                        ],
                    ],
                ],
            ]);
        });
    }

    public function testDoesNotShortcutForeignKeySelectionUsingUnionSelectIDAndTypename(): void
    {
        config(['lighthouse.shortcut_foreign_key_selection' => true]);

        $user = factory(User::class)->create();
        $this->assertInstanceOf(User::class, $user);

        $task = factory(Task::class)->make();
        $this->assertInstanceOf(Task::class, $task);
        $task->user()->associate($user);
        $task->save();

        $image = factory(Image::class)->make();
        $this->assertInstanceOf(Image::class, $image);
        $image->imageable()->associate($task);
        $image->save();

        $post = factory(Post::class)->make();
        $this->assertInstanceOf(Post::class, $post);
        $post->user()->associate($user);
        $post->save();

        $postImage = factory(Image::class)->make();
        $this->assertInstanceOf(Image::class, $postImage);
        $postImage->imageable()->associate($post);
        $postImage->save();

        $this->schema = /** @lang GraphQL */ <<<'GRAPHQL'
        union Imageable = Task | Post

        type Task {
            id: ID!
            name: String!
        }

        type Post {
            id: ID!
            title: String!
        }

        type Image {
            id: ID!
            imageable: Imageable! @morphTo
        }

        type Query {
            image (
                id: ID! @eq
            ): Image @find
        }
        GRAPHQL;

        $this->assertQueryCountMatches(4, function () use ($image, $postImage, $task, $post): void {
            $this->graphQL(/** @lang GraphQL */ <<<'GRAPHQL'
                query ($taskImage: ID!, $postImage: ID!) {
                    taskImage: image(id: $taskImage) {
                        id
                        imageable {
                            ... on Task {
                                id
                                name
                            }
                            ... on Post {
                                id
                                title
                            }
                        }
                    }
                    postImage: image(id: $postImage) {
                        id
                        imageable {
                            ... on Task {
                                id
                                name
                            }
                            ... on Post {
                                id
                                title
                            }
                        }
                    }
                }
            GRAPHQL, [
                'taskImage' => $image->id,
                'postImage' => $postImage->id,
            ])->assertJson([
                'data' => [
                    'taskImage' => [
                        'id' => $image->id,
                        'imageable' => [
                            'id' => $task->id,
                            'name' => $task->name,
                        ],
                    ],
                    'postImage' => [
                        'id' => $postImage->id,
                        'imageable' => [
                            'id' => $post->id,
                            'title' => $post->title,
                        ],
                    ],
                ],
            ]);
        });
    }

    public function testShortcutsForeignKeySelectID(): void
    {
        config(['lighthouse.shortcut_foreign_key_selection' => true]);

        $user = factory(User::class)->create();
        $this->assertInstanceOf(User::class, $user);

        $task = factory(Task::class)->make();
        $this->assertInstanceOf(Task::class, $task);
        $task->user()->associate($user);
        $task->save();

        $image = factory(Image::class)->make();
        $this->assertInstanceOf(Image::class, $image);
        $image->imageable()->associate($task);
        $image->save();

        $this->schema = /** @lang GraphQL */ <<<'GRAPHQL'
        type Image {
            id: ID!
            imageable: Task! @morphTo
        }

        type Task {
            id: ID!
            name: String!
        }

        type Query {
            image (
                id: ID! @eq
            ): Image @find
        }
        GRAPHQL;

        $this->assertQueryCountMatches(1, function () use ($image, $task): void {
            $this->graphQL(/** @lang GraphQL */ <<<'GRAPHQL'
                query ($id: ID!) {
                    image(id: $id) {
                        id
                        imageable {
                            id
                        }
                    }
                }
            GRAPHQL,
                [
                    'id' => $image->id,
                ],
            )->assertJson([
                'data' => [
                    'image' => [
                        'id' => $image->id,
                        'imageable' => [
                            'id' => $task->id,
                        ],
                    ],
                ],
            ]);
        });
    }

    public function testShortcutsForeignKeySelectTypename(): void
    {
        config(['lighthouse.shortcut_foreign_key_selection' => true]);

        $user = factory(User::class)->create();
        $this->assertInstanceOf(User::class, $user);

        $task = factory(Task::class)->make();
        $this->assertInstanceOf(Task::class, $task);
        $task->user()->associate($user);
        $task->save();

        $image = factory(Image::class)->make();
        $this->assertInstanceOf(Image::class, $image);
        $image->imageable()->associate($task);
        $image->save();

        $this->schema = /** @lang GraphQL */ <<<'GRAPHQL'
        type Image {
            id: ID!
            imageable: Task! @morphTo
        }

        type Task {
            id: ID!
            name: String!
        }

        type Query {
            image (
                id: ID! @eq
            ): Image @find
        }
        GRAPHQL;

        $this->assertQueryCountMatches(1, function () use ($image): void {
            $this->graphQL(/** @lang GraphQL */ <<<'GRAPHQL'
                query ($id: ID!) {
                    image(id: $id) {
                        id
                        imageable {
                            __typename
                        }
                    }
                }
            GRAPHQL,
                [
                    'id' => $image->id,
                ],
            )->assertJson([
                'data' => [
                    'image' => [
                        'id' => $image->id,
                        'imageable' => [
                            '__typename' => 'Task',
                        ],
                    ],
                ],
            ]);
        });
    }

    public function testShortcutsForeignKeySelectIDAndTypename(): void
    {
        config(['lighthouse.shortcut_foreign_key_selection' => true]);

        $user = factory(User::class)->create();
        $this->assertInstanceOf(User::class, $user);

        $task = factory(Task::class)->make();
        $this->assertInstanceOf(Task::class, $task);
        $task->user()->associate($user);
        $task->save();

        $image = factory(Image::class)->make();
        $this->assertInstanceOf(Image::class, $image);
        $image->imageable()->associate($task);
        $image->save();

        $this->schema = /** @lang GraphQL */ <<<'GRAPHQL'
        type Image {
            id: ID!
            imageable: Task! @morphTo
        }

        type Task {
            id: ID!
            name: String!
        }

        type Query {
            image (
                id: ID! @eq
            ): Image @find
        }
        GRAPHQL;

        $this->assertQueryCountMatches(1, function () use ($image, $task): void {
            $this->graphQL(/** @lang GraphQL */ <<<'GRAPHQL'
                query ($id: ID!) {
                    image(id: $id) {
                        id
                        imageable {
                            __typename
                            id
                        }
                    }
                }
            GRAPHQL,
                [
                    'id' => $image->id,
                ],
            )->assertJson([
                'data' => [
                    'image' => [
                        'id' => $image->id,
                        'imageable' => [
                            '__typename' => 'Task',
                            'id' => $task->id,
                        ],
                    ],
                ],
            ]);
        });
    }

    public function testDoesNotShortcutForeignKeyIfQueryHasFieldSelection(): void
    {
        config(['lighthouse.shortcut_foreign_key_selection' => true]);

        $user = factory(User::class)->create();
        $this->assertInstanceOf(User::class, $user);

        $task = factory(Task::class)->make();
        $this->assertInstanceOf(Task::class, $task);
        $task->user()->associate($user);
        $task->save();

        $image = factory(Image::class)->make();
        $this->assertInstanceOf(Image::class, $image);
        $image->imageable()->associate($task);
        $image->save();

        $this->schema = /** @lang GraphQL */ <<<'GRAPHQL'
        type Image {
            id: ID!
            imageable: Task! @morphTo
        }

        type Task {
            id: ID!
            name: String!
        }

        type Query {
            image (
                id: ID! @eq
            ): Image @find
        }
        GRAPHQL;

        $this->assertQueryCountMatches(2, function () use ($image, $task): void {
            $this->graphQL(/** @lang GraphQL */ <<<'GRAPHQL'
                query ($id: ID!) {
                    image(id: $id) {
                        id
                        imageable {
                            id
                            name
                        }
                    }
                }
            GRAPHQL,
                [
                    'id' => $image->id,
                ],
            )->assertJson([
                'data' => [
                    'image' => [
                        'id' => $image->id,
                        'imageable' => [
                            'id' => $task->id,
                            'name' => $task->name,
                        ],
                    ],
                ],
            ]);
        });
    }
}
