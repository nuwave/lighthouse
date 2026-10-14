<?php declare(strict_types=1);

namespace Tests\Integration\Execution\MutationExecutor;

use Nuwave\Lighthouse\Execution\Arguments\ParentRelationScope;
use Tests\DBTestCase;
use Tests\Utils\Models\Tag;
use Tests\Utils\Models\Task;

final class MorphToManyTest extends DBTestCase
{
    protected string $schema = /** @lang GraphQL */ <<<'GRAPHQL'
    type Mutation {
        createTask(input: CreateTaskInput! @spread): Task @create
        upsertTask(input: UpsertTaskInput! @spread): Task @upsert
    }

    input CreateTaskInput {
        name: String!
        tags: CreateTagRelation
    }

    input CreateTagRelation {
        create: [CreateTagInput!]
        upsert: [UpsertTagInput!]
        sync: [ID!]
        connect: [ID!]
    }

    input CreateTagInput {
        name: String!
    }

    input UpsertTaskInput {
        id: ID
        name: String!
        tags: UpsertTagRelation
    }

    input UpsertTagRelation {
        create: [CreateTagInput!]
        upsert: [UpsertTagInput!]
        sync: [ID!]
        connect: [ID!]
    }

    input UpsertTagInput {
        id: ID
        name: String!
    }

    type Task {
        id: ID!
        name: String!
        tags: [Tag!]!
    }

    type Tag {
        id: ID!
        name: String!
    }
    GRAPHQL . self::PLACEHOLDER_QUERY;

    public function testCreateATaskWithExistingTagsByUsingConnect(): void
    {
        $tag = factory(Tag::class)->make();
        $this->assertInstanceOf(Tag::class, $tag);
        $tag->name = 'php';
        $tag->save();

        $this->graphQL(/** @lang GraphQL */ <<<'GRAPHQL'
        mutation {
            createTask(input: {
                name: "Finish tests"
                tags: {
                    connect: [1]
                }
            }) {
                tags {
                    id
                }
            }
        }
        GRAPHQL)->assertJson([
            'data' => [
                'createTask' => [
                    'tags' => [
                        [
                            'id' => $tag->id,
                        ],
                    ],
                ],
            ],
        ]);
    }

    public function testAllowsNullOperations(): void
    {
        $this->graphQL(/** @lang GraphQL */ <<<'GRAPHQL'
        mutation {
            createTask(input: {
                name: "Finish tests"
                tags: {
                    create: null
                    upsert: null
                    sync: null
                    connect: null
                }
            }) {
                name
                tags {
                    id
                }
            }
        }
        GRAPHQL)->assertJson([
            'data' => [
                'createTask' => [
                    'name' => 'Finish tests',
                    'tags' => [],
                ],
            ],
        ]);
    }

    public function testUpsertATaskWithExistingTagsByUsingConnect(): void
    {
        $tag = factory(Tag::class)->make();
        $this->assertInstanceOf(Tag::class, $tag);
        $tag->name = 'php';
        $tag->save();

        $this->graphQL(/** @lang GraphQL */ <<<'GRAPHQL'
        mutation {
            upsertTask(input: {
                id: 1
                name: "Finish tests"
                tags: {
                    connect: [1]
                }
            }) {
                tags{
                    id
                }
            }
        }
        GRAPHQL)->assertJson([
            'data' => [
                'upsertTask' => [
                    'tags' => [
                        [
                            'id' => $tag->id,
                        ],
                    ],
                ],
            ],
        ]);
    }

    public function testCreateATaskWithExistingTagsByUsingSync(): void
    {
        $tag = factory(Tag::class)->make();
        $this->assertInstanceOf(Tag::class, $tag);
        $tag->name = 'php';
        $tag->save();

        $this->graphQL(/** @lang GraphQL */ <<<'GRAPHQL'
        mutation {
            createTask(input: {
                name: "Finish tests"
                tags: {
                    sync: [1]
                }
            }) {
                tags {
                    id
                }
            }
        }
        GRAPHQL)->assertJson([
            'data' => [
                'createTask' => [
                    'tags' => [
                        [
                            'id' => $tag->id,
                        ],
                    ],
                ],
            ],
        ]);
    }

    public function testUpsertATaskWithExistingTagsByUsingSync(): void
    {
        $tag = factory(Tag::class)->make();
        $this->assertInstanceOf(Tag::class, $tag);
        $tag->name = 'php';
        $tag->save();

        $this->graphQL(/** @lang GraphQL */ <<<'GRAPHQL'
        mutation {
            upsertTask(input: {
                id: 1
                name: "Finish tests"
                tags: {
                    sync: [1]
                }
            }) {
                tags {
                    id
                }
            }
        }
        GRAPHQL)->assertJson([
            'data' => [
                'upsertTask' => [
                    'tags' => [
                        [
                            'id' => $tag->id,
                        ],
                    ],
                ],
            ],
        ]);
    }

    public function testCreateANewTagRelationByUsingCreate(): void
    {
        $this->graphQL(/** @lang GraphQL */ <<<'GRAPHQL'
        mutation {
            createTask(input: {
                name: "Finish tests"
                tags: {
                    create: [
                        {
                            name: "php"
                        }
                    ]
                }
            }) {
                tags {
                    id
                    name
                }
            }
        }
        GRAPHQL)->assertJson([
            'data' => [
                'createTask' => [
                    'tags' => [
                        [
                            'id' => 1,
                            'name' => 'php',
                        ],
                    ],
                ],
            ],
        ]);
    }

    public function testUpsertANewTagRelationByUsingCreate(): void
    {
        $this->graphQL(/** @lang GraphQL */ <<<'GRAPHQL'
        mutation {
            upsertTask(input: {
                id: 1
                name: "Finish tests"
                tags: {
                    create: [
                        {
                            name: "php"
                        }
                    ]
                }
            }) {
                tags {
                    id
                    name
                }
            }
        }
        GRAPHQL)->assertJson([
            'data' => [
                'upsertTask' => [
                    'tags' => [
                        [
                            'id' => 1,
                            'name' => 'php',
                        ],
                    ],
                ],
            ],
        ]);
    }

    public function testUpsertANewTagRelationByUsingUpsert(): void
    {
        $this->graphQL(/** @lang GraphQL */ <<<'GRAPHQL'
        mutation {
            upsertTask(input: {
                id: 1
                name: "Finish tests"
                tags: {
                    upsert: [
                        {
                            id: 1
                            name: "php"
                        }
                    ]
                }
            }) {
                tags {
                    id
                    name
                }
            }
        }
        GRAPHQL)->assertJson([
            'data' => [
                'upsertTask' => [
                    'tags' => [
                        [
                            'id' => 1,
                            'name' => 'php',
                        ],
                    ],
                ],
            ],
        ]);
    }

    public function testUpsertMorphToManyWithoutId(): void
    {
        $this->graphQL(/** @lang GraphQL */ <<<'GRAPHQL'
        mutation {
            upsertTask(input: {
                name: "Finish tests"
                tags: {
                    upsert: [
                        {
                            name: "php"
                        }
                    ]
                }
            }) {
                id
                tags {
                    id
                    name
                }
            }
        }
        GRAPHQL)->assertJson([
            'data' => [
                'upsertTask' => [
                    'id' => 1,
                    'tags' => [
                        [
                            'id' => 1,
                            'name' => 'php',
                        ],
                    ],
                ],
            ],
        ]);
    }

    public function testUpsertMorphToManyWithoutRelationFailsWhenScopedToParent(): void
    {
        config(['lighthouse.nested_mutations_scope_to_parent' => true]);

        $task = factory(Task::class)->create();
        $otherTask = factory(Task::class)->create();
        $otherTasksTag = factory(Tag::class)->make();
        $otherTasksTag->name = 'original';
        $otherTasksTag->save();
        $otherTask->tags()->attach($otherTasksTag);

        $this->graphQL(/** @lang GraphQL */ <<<'GRAPHQL'
        mutation ($taskID: ID!, $tagID: ID!) {
            upsertTask(input: {
                id: $taskID
                name: "task"
                tags: {
                    upsert: [{ id: $tagID, name: "hacked" }]
                }
            }) {
                id
            }
        }
        GRAPHQL, [
            'taskID' => $task->id,
            'tagID' => $otherTasksTag->id,
        ])->assertGraphQLErrorMessage(ParentRelationScope::UNRELATED_MODEL);

        $otherTasksTag->refresh();

        $this->assertSame('original', $otherTasksTag->name);
        $this->assertCount(0, $task->tags()->get());
    }
}
