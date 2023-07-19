<?php declare(strict_types=1);

namespace Tests\Integration\Schema\Directives;

use Illuminate\Foundation\Testing\WithFaker;
use Illuminate\Support\Collection;
use Tests\DBTestCase;
use Tests\Utils\Models\Post;
use Tests\Utils\Models\Tag;
use Tests\Utils\Models\Task;
use Tests\Utils\Models\User;

final class MorphToManyDirectiveTest extends DBTestCase
{
    use WithFaker;

    protected Post $post;

    /** @var \Illuminate\Support\Collection<int, \Tests\Utils\Models\Tag> */
    protected Collection $postTags;

    protected function setUp(): void
    {
        parent::setUp();

        $this->post = factory(Post::class)->create();
        $this->postTags = Collection::times($this->faker->numberBetween(3, 7), function () {
            $tag = factory(Tag::class)->create();
            $this->post->tags()->attach($tag);

            return $tag;
        });
    }

    public function testResolveMorphToManyRelationship(): void
    {
        $this->schema = /** @lang GraphQL */ '
        type Tag {
            id: ID!
            name: String!
        }

        type Post {
            id: ID!
            tags: [Tag]! @morphToMany(relation: "tags")
        }

        type Task {
            id: ID!
            tags: [Tag]! @morphToMany(relation: "tags")
        }

        type Query {
            post (
                id: ID! @eq
            ): Post @find
        }
        ';

        $this->graphQL(/** @lang GraphQL */ '
        query ($id: ID!) {
            post(id: $id) {
                id
                tags {
                    id
                    name
                }
            }
        }
        ', [
            'id' => $this->post->id,
        ])->assertJson([
            'data' => [
                'post' => [
                    'id' => $this->post->id,
                    'tags' => $this->postTags
                        ->map(static fn (Tag $tag): array => [
                            'id' => $tag->id,
                            'name' => $tag->name,
                        ])
                        ->toArray(),
                ],
            ],
        ]);
    }

    public function testResolveMorphToManyRelationshipWithRelayConnection(): void
    {
        $this->schema = /** @lang GraphQL */ '
        type Tag {
            id: ID!
            name: String!
        }

        type Post {
            id: ID!
            tags: [Tag!]! @morphToMany(relation: "tags", type: CONNECTION)
        }

        type Query {
            post (
                id: ID! @eq
            ): Post @find
        }
        ';

        $this->graphQL(/** @lang GraphQL */ '
        query ($id: ID!) {
            post(id: $id) {
                id
                tags(first: 7) {
                    edges {
                        node {
                            id
                            ...on Tag {
                                name
                            }
                        }
                    }
                }
            }
        }
        ', [
            'id' => $this->post->id,
        ])->assertJson([
            'data' => [
                'post' => [
                    'id' => $this->post->id,
                    'tags' => [
                        'edges' => $this->postTags
                            ->map(static fn (Tag $tag): array => [
                                'node' => [
                                    'id' => $tag->id,
                                    'name' => $tag->name,
                                ],
                            ])
                            ->toArray(),
                    ],
                ],
            ],
        ]);
    }

    public function testResolveMorphToManyWithCustomName(): void
    {
        $this->schema = /** @lang GraphQL */ '
        type Tag {
            id: ID!
            name: String!
        }

        type Post {
            id: ID!
            customTags: [Tag]! @morphToMany(relation: "tags")
        }

        type Task {
            id: ID!
            tags: [Tag]! @morphToMany(relation: "tags")
        }

        type Query {
            post (
                id: ID! @eq
            ): Post @find
        }
        ';

        $this->graphQL(/** @lang GraphQL */ '
        query ($id: ID!) {
            post(id: $id) {
                id
                customTags {
                    id
                    name
                }
            }
        }
        ', [
            'id' => $this->post->id,
        ])->assertJson([
            'data' => [
                'post' => [
                    'id' => $this->post->id,
                    'customTags' => $this->postTags
                        ->map(static fn (Tag $tag): array => [
                            'id' => $tag->id,
                            'name' => $tag->name,
                        ])
                        ->toArray(),
                ],
            ],
        ]);
    }

    public function testResolveMorphToManyUsingInterfaces(): void
    {
        $user = factory(User::class)->create();
        assert($user instanceof User);

        $post = factory(Post::class)->make();
        assert($post instanceof Post);
        $post->user()->associate($user);
        $post->save();

        /** @var \Illuminate\Database\Eloquent\Collection<int, \Tests\Utils\Models\Tag> $postTags */
        $postTags = factory(Tag::class, 3)
            ->create()
            ->map(static function (Tag $tag) use ($post): Tag {
                $post->tags()->attach($tag);

                return $tag;
            });

        $task = factory(Task::class)->make();
        assert($task instanceof Task);
        $task->user()->associate($user);
        $task->save();

        /** @var \Illuminate\Database\Eloquent\Collection<int, \Tests\Utils\Models\Tag> $taskTags */
        $taskTags = factory(Tag::class, 3)
            ->create()
            ->map(static function (Tag $tag) use ($task): Tag {
                $task->tags()->attach($tag);

                return $tag;
            });

        $this->schema = /** @lang GraphQL */ <<<GRAPHQL
        interface Tag @interface(resolveType: "{$this->qualifyTestResolver('resolveType')}") {
            id: ID!
        }

        type PostTag implements Tag {
            id: ID!
            name: String!
        }

        type TaskTag implements Tag {
            id: ID!
            title: String! @rename(attribute: "name")
        }

        type Post {
            id: ID!
            tags: [Tag]! @morphToMany(relation: "tags")
        }

        type Task {
            id: ID!
            tags: [Tag]! @morphToMany(relation: "tags")
        }

        type User {
            id: ID!
            posts: [Post]! @hasMany
            tasks: [Task]! @hasMany
        }

        type Query {
            user (
                id: ID! @eq
            ): User @find
        }
        GRAPHQL;

        $this->graphQL(/** @lang GraphQL */ '
        query ($userId: ID!){
            user (id: $userId) {
                id
                posts {
                    id
                    tags {
                        ... on PostTag {
                            id
                            name
                        }

                        ... on TaskTag {
                            id
                            title
                        }
                    }
                }
                tasks {
                    id
                    tags {
                        ... on PostTag {
                            id
                            name
                        }

                        ... on TaskTag {
                            id
                            title
                        }
                    }
                }
            }
        }
        ', [
            'userId' => $user->id,
        ])->assertJson([
            'data' => [
                'user' => [
                    'id' => $user->id,
                    'posts' => [
                        [
                            'id' => $post->id,
                            'tags' => $postTags
                                ->map(static fn (Tag $tag): array => [
                                    'id' => $tag->id,
                                    'name' => $tag->name,
                                ])
                                ->all(),
                        ],
                    ],
                    'tasks' => [
                        [
                            'id' => $task->id,
                            'tags' => $taskTags
                                ->map(static fn (Tag $tag): array => [
                                    'id' => $tag->id,
                                    'title' => $tag->name,
                                ])
                                ->toArray(),
                        ],
                    ],
                ],
            ],
        ]);
    }

    public static function resolveType(Tag $root): string
    {
        return $root->posts()->exists()
            ? 'PostTag'
            : 'TaskTag';
    }
}
