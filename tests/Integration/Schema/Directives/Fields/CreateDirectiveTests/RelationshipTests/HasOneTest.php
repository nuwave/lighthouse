<?php

namespace Tests\Integration\Schema\Directives\Fields\CreateDirectiveTests\RelationshipTests;

use Tests\DBTestCase;
use Tests\Utils\Models\User;

class HasOneTest extends DBTestCase
{
    /**
     * @test
     */
    public function itCanCreateWithNewHasOne(): void
    {
        factory(User::class)->create();

        $this->schema = '
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
            createTask(input: CreateTaskInput!): Task @create(flatten: true)
        }
        
        input CreatePostRelation {
            create: CreatePostInput!
        }
        
        input CreatePostRelation {
            create: CreatePostInput!
        }
        
        input CreateTaskInput {
            name: String!
            user_id: ID!
            post: CreatePostRelation
        }
        
        input CreatePostInput {
            title: String!
            user_id: ID!
            body: String!
        }
        '.$this->placeholderQuery();

        $this->query('
        mutation {
            createTask(input: {
                name: "foo"
                user_id: 1
                post: {
                    create: {
                        title: "bar"
                        user_id: 1
                        body: "foobar"
                    }
                }
            }) {
                id
                name
                post {
                    id
                    body
                }
            }
        }
        ')->assertJson([
            'data' => [
                'createTask' => [
                    'id' => '1',
                    'name' => 'foo',
                    'post' => [
                        'id' => '1',
                        'body' => 'foobar'
                    ]
                ]
            ]
        ]);
    }
}
