<?php declare(strict_types=1);

namespace Tests\Integration\Schema\Directives;

use Illuminate\Foundation\Testing\WithFaker;
use Illuminate\Support\Collection;
use Tests\DBTestCase;
use Tests\Utils\Models\Post;
use Tests\Utils\Models\Tag;
use Tests\Utils\Models\Task;

final class MorphedByManyDirectiveTest extends DBTestCase
{
    use WithFaker;

    protected Tag $tag;

    /** @var \Illuminate\Support\Collection<int, \Tests\Utils\Models\Post> */
    protected Collection $tagPosts;

    /** @var \Illuminate\Support\Collection<int, \Tests\Utils\Models\Task> */
    protected Collection $tagTasks;

    protected function setUp(): void
    {
        parent::setUp();

        $this->tag = factory(Tag::class)->create();

        $this->tagPosts = Collection::times($this->faker->numberBetween(3, 7), function (): Post {
            $post = factory(Post::class)->create();
            $post->tags()->attach($this->tag);

            return $post;
        });

        $this->tagTasks = Collection::times($this->faker->numberBetween(3, 7), function (): Task {
            $task = factory(Task::class)->create();
            $task->tags()->attach($this->tag);

            return $task;
        });

        // Not related to the tag, must never show up in results
        factory(Post::class)->create();
        factory(Task::class)->create();
    }

    public function testResolveMorphedByManyRelationship(): void
    {
        $this->schema = /** @lang GraphQL */ <<<'GRAPHQL'
        type Post {
            id: ID!
        }

        type Task {
            id: ID!
        }

        type Tag {
            id: ID!
            posts: [Post!]! @morphedByMany
            tasks: [Task!]! @morphedByMany
        }

        type Query {
            tag(id: ID! @eq): Tag @find
        }
        GRAPHQL;

        $this->graphQL(/** @lang GraphQL */ <<<'GRAPHQL'
        query ($id: ID!) {
            tag(id: $id) {
                id
                posts {
                    id
                }
                tasks {
                    id
                }
            }
        }
        GRAPHQL, [
            'id' => $this->tag->id,
        ])->assertExactJson([
            'data' => [
                'tag' => [
                    'id' => (string) $this->tag->id,
                    'posts' => $this->tagPosts
                        ->map(static fn (Post $post): array => ['id' => (string) $post->id])
                        ->all(),
                    'tasks' => $this->tagTasks
                        ->map(static fn (Task $task): array => ['id' => (string) $task->id])
                        ->all(),
                ],
            ],
        ]);
    }

    public function testResolveMorphedByManyWithCustomName(): void
    {
        $this->schema = /** @lang GraphQL */ <<<'GRAPHQL'
        type Post {
            id: ID!
        }

        type Tag {
            id: ID!
            taggedPosts: [Post!]! @morphedByMany(relation: "posts")
        }

        type Query {
            tag(id: ID! @eq): Tag @find
        }
        GRAPHQL;

        $this->graphQL(/** @lang GraphQL */ <<<'GRAPHQL'
        query ($id: ID!) {
            tag(id: $id) {
                taggedPosts {
                    id
                }
            }
        }
        GRAPHQL, [
            'id' => $this->tag->id,
        ])->assertExactJson([
            'data' => [
                'tag' => [
                    'taggedPosts' => $this->tagPosts
                        ->map(static fn (Post $post): array => ['id' => (string) $post->id])
                        ->all(),
                ],
            ],
        ]);
    }

    public function testResolveMorphedByManyWithRelayConnection(): void
    {
        $this->schema = /** @lang GraphQL */ <<<'GRAPHQL'
        type Post {
            id: ID!
        }

        type Tag {
            id: ID!
            posts: [Post!]! @morphedByMany(type: CONNECTION)
        }

        type Query {
            tag(id: ID! @eq): Tag @find
        }
        GRAPHQL;

        $this->graphQL(/** @lang GraphQL */ <<<'GRAPHQL'
        query ($id: ID!) {
            tag(id: $id) {
                posts(first: 2) {
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
        GRAPHQL, [
            'id' => $this->tag->id,
        ])->assertExactJson([
            'data' => [
                'tag' => [
                    'posts' => [
                        'pageInfo' => [
                            'hasNextPage' => $this->tagPosts->count() > 2,
                        ],
                        'edges' => $this->tagPosts
                            ->take(2)
                            ->map(static fn (Post $post): array => ['node' => ['id' => (string) $post->id]])
                            ->values()
                            ->all(),
                    ],
                ],
            ],
        ]);
    }

    public function testResolveMorphedByManyWithPaginator(): void
    {
        $this->schema = /** @lang GraphQL */ <<<'GRAPHQL'
        type Task {
            id: ID!
        }

        type Tag {
            id: ID!
            tasks: [Task!]! @morphedByMany(type: PAGINATOR)
        }

        type Query {
            tag(id: ID! @eq): Tag @find
        }
        GRAPHQL;

        $this->graphQL(/** @lang GraphQL */ <<<'GRAPHQL'
        query ($id: ID!) {
            tag(id: $id) {
                tasks(first: 2) {
                    paginatorInfo {
                        total
                    }
                    data {
                        id
                    }
                }
            }
        }
        GRAPHQL, [
            'id' => $this->tag->id,
        ])->assertExactJson([
            'data' => [
                'tag' => [
                    'tasks' => [
                        'paginatorInfo' => [
                            'total' => $this->tagTasks->count(),
                        ],
                        'data' => $this->tagTasks
                            ->take(2)
                            ->map(static fn (Task $task): array => ['id' => (string) $task->id])
                            ->values()
                            ->all(),
                    ],
                ],
            ],
        ]);
    }
}
