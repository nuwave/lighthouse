<?php

namespace Tests\Integration\Execution\MutationExecutor;

use Tests\DBTestCase;
use Tests\Utils\Models\Tag;

class MorphToManyTest extends DBTestCase
{
    protected $schema = /** @lang GraphQL */ '
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
    '.self::PLACEHOLDER_QUERY;

    public function testCanCreateATaskWithExistingTagsByUsingConnect(): void
    {
        $id = factory(Tag::class)->create(['name' => 'php'])->id;

        $this->graphQL(/** @lang GraphQL */ '
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
        ')->assertJson([
            'data' => [
                'createTask' => [
                    'tags' => [
                        [
                            'id' => $id,
                        ],
                    ],
                ],
            ],
        ]);
    }

    public function testAllowsNullOperations(): void
    {
        $this->graphQL(/** @lang GraphQL */ '
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
        ')->assertJson([
            'data' => [
                'createTask' => [
                    'name' => 'Finish tests',
                    'tags' => [],
                ],
            ],
        ]);
    }

    public function testCanUpsertATaskWithExistingTagsByUsingConnect(): void
    {
        $id = factory(Tag::class)->create(['name' => 'php'])->id;

        $this->graphQL('
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
        ')->assertJson([
            'data' => [
                'upsertTask' => [
                    'tags' => [
                        [
                            'id' => $id,
                        ],
                    ],
                ],
            ],
        ]);
    }

    public function testCanCreateATaskWithExistingTagsByUsingSync(): void
    {
        $id = factory(Tag::class)->create(['name' => 'php'])->id;

        $this->graphQL('
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
        ')->assertJson([
            'data' => [
                'createTask' => [
                    'tags' => [
                        [
                            'id' => $id,
                        ],
                    ],
                ],
            ],
        ]);
    }

    public function testCanUpsertATaskWithExistingTagsByUsingSync(): void
    {
        $id = factory(Tag::class)->create(['name' => 'php'])->id;

        $this->graphQL('
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
        ')->assertJson([
            'data' => [
                'upsertTask' => [
                    'tags' => [
                        [
                            'id' => $id,
                        ],
                    ],
                ],
            ],
        ]);
    }

    public function testCanCreateANewTagRelationByUsingCreate(): void
    {
        $this->graphQL('
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
        ')->assertJson([
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

    public function testCanUpsertANewTagRelationByUsingCreate(): void
    {
        $this->graphQL('
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
        ')->assertJson([
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

    public function testCanUpsertANewTagRelationByUsingUpsert(): void
    {
        $this->graphQL('
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
        ')->assertJson([
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
GRAPHQL
        )->assertJson([
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
}
