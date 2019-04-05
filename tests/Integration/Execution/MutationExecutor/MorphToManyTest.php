<?php

namespace Tests\Integration\Execution\MutationExecutor;

use Tests\DBTestCase;
use Tests\Utils\Models\Tag;

class MorphToManyTest extends DBTestCase
{
    protected $schema = '
    type Task {
        id: ID!
        name: String!
        tags: [Tag!]!
    }
    
    type Tag {
        id: ID!
        name: String!
    }
    
    input CreateTagInput {
        name: String!
    }
    
    input CreateTagRelation {
        create: [CreateTagInput!]
        sync: [ID!]
        connect: [ID!]
    }
    
    input CreateTaskInput {
        name: String!
        tags: CreateTagRelation
    }
    
    type Mutation {
        createTask(input: CreateTaskInput!): Task @create(flatten: true)
    }
    ';

    public function setUp(): void
    {
        parent::setUp();

        $this->schema .= $this->placeholderQuery();
    }

    /**
     * @test
     */
    public function itCanCreateATaskWithExistingTagsByUsingConnect(){
        $id = factory(Tag::class)->create(['name' => 'php'])->id;

        $this->query('
        mutation {
            createTask(input: {
                name: "Finish tests"
                tags: {
                    connect: [1]
                }
            }){
                tags{
                        id
                    }
                }
            }
            ')->assertJson([
                'data'=> [
                    'createTask' => [
                        'tags' => [
                            [
                            'id' => $id
                            ]
                        ]
                    ]
                ]
        ]);
    }

    /**
     * @test
     */
    public function itCanCreateATaskWithExistingTagsByUsingSync(){
        $id = factory(Tag::class)->create(['name' => 'php'])->id;

        $this->query('
        mutation {
            createTask(input: {
                name: "Finish tests"
                tags: {
                    sync: [1]
                }
            }){
                tags{
                        id
                    }
                }
            }
            ')->assertJson([
            'data'=> [
                'createTask' => [
                    'tags' => [
                        [
                            'id' => $id
                        ]
                    ]
                ]
            ]
        ]);
    }

    /**
     * @test
     */
    public function itCanCreateANewTagRelationByUsingCreate(){
        $this->query('
        mutation {
            createTask(input: {
                name: "Finish tests"
                tags: {
                    create: [{name: "php"}]
                }
            }){
                tags{
                        id
                        name
                    }
                }
            }
            ')->assertJson([
            'data'=> [
                'createTask' => [
                    'tags' => [
                        [
                            'id' => 1,
                            'name' => 'php'
                        ]
                    ]
                ]
            ]
        ]);
    }
}
