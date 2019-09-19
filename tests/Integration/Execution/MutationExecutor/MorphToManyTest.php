<?php

namespace Tests\Integration\Execution\MutationExecutor;

use Tests\DBTestCase;
use Tests\Utils\Models\Tag;

class MorphToManyTest extends DBTestCase
{
    protected $schema = '
    type Mutation {
        createTask(input: CreateTaskInput! @spread): Task @create
    }
    
    input CreateTaskInput {
        name: String!
        tags: CreateTagRelation
    }
    
    input CreateTagRelation {
        create: [CreateTagInput!]
        sync: [ID!]
        connect: [ID!]
    }
    
    input CreateTagInput {
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
    ';

    public function setUp(): void
    {
        parent::setUp();

        $this->schema .= $this->placeholderQuery();
    }

    public function testCanCreateATaskWithExistingTagsByUsingConnect(): void
    {
        $id = factory(Tag::class)->create(['name' => 'php'])->id;

        $this->graphQL('
        mutation {
            createTask(input: {
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
}
