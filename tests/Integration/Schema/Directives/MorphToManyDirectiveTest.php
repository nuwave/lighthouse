<?php

namespace Tests\Integration\Schema\Directives;

use Illuminate\Foundation\Testing\WithFaker;
use Illuminate\Support\Collection;
use Tests\DBTestCase;
use Tests\Utils\Models\Post;
use Tests\Utils\Models\Tag;
use Tests\Utils\Models\Task;
use Tests\Utils\Models\User;

class MorphToManyDirectiveTest extends DBTestCase
{
    use WithFaker;

    /**
     * @var \Tests\Utils\Models\Post
     */
    protected $post;

    /**
     * @var \Illuminate\Database\Eloquent\Collection|\Illuminate\Support\Collection
     */
    protected $postTags;

    public function setUp(): void
    {
        parent::setUp();

        $this->post = factory(Post::class)->create();
        $this->postTags = Collection::times($this->faker->numberBetween(3, 7))->map(function () {
            $tag = factory(Tag::class)->create();
            $this->post->tags()->attach($tag);

            return $tag;
        });
    }

    public function testCanResolveMorphToManyRelationship(): void
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
                    'tags' => $this->postTags->map(function (Tag $tag) {
                        return [
                            'id' => $tag->id,
                            'name' => $tag->name,
                        ];
                    })->toArray(),
                ],
            ],
        ]);
    }

    public function testCanResolveMorphToManyWithCustomName(): void
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
                    'customTags' => $this->postTags->map(function (Tag $tag) {
                        return [
                            'id' => $tag->id,
                            'name' => $tag->name,
                        ];
                    })->toArray(),
                ],
            ],
        ]);
    }

    public function testCanResolveMorphToManyUsingInterfaces(): void
    {
        /** @var \Tests\Utils\Models\User $user */
        $user = factory(User::class)->create();
        /** @var \Tests\Utils\Models\Post $post */
        $post = factory(Post::class)->create([
            'user_id' => $user->id,
        ]);
        /** @var \Illuminate\Database\Eloquent\Collection $postTags */
        $postTags = factory(Tag::class, 3)->create()->map(function (Tag $tag) use ($post) {
            $post->tags()->attach($tag);

            return $tag;
        });
        /** @var \Tests\Utils\Models\Task $task */
        $task = factory(Task::class)->create([
            'user_id' => $user->id,
        ]);
        /** @var \Illuminate\Database\Eloquent\Collection $taskTags */
        $taskTags = factory(Tag::class, 3)->create()->map(function (Tag $tag) use ($task) {
            $task->tags()->attach($tag);

            return $tag;
        });

        $this->schema = /** @lang GraphQL */ '
        interface Tag @interface(resolveType: "'.$this->qualifyTestResolver('resolveType').'") {
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
        ';

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
                            'tags' => $postTags->map(function (Tag $tag) {
                                return [
                                    'id' => $tag->id,
                                    'name' => $tag->name,
                                ];
                            })->toArray(),
                        ],
                    ],
                    'tasks' => [
                        [
                            'id' => $task->id,
                            'tags' => $taskTags->map(function (Tag $tag) {
                                return [
                                    'id' => $tag->id,
                                    'title' => $tag->name,
                                ];
                            })->toArray(),
                        ],
                    ],
                ],
            ],
        ]);
    }

    public function resolveType($root): string
    {
        return $root->posts()->count() ? 'PostTag' : 'TaskTag';
    }
}
