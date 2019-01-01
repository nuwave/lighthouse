<?php

namespace Tests\Integration\Schema\Directives\Fields\CreateDirectiveTests\RelationshipTests;

use Tests\DBTestCase;
use Illuminate\Support\Arr;
use Tests\Utils\Models\Post;
use Tests\Utils\Models\User;

class HasOneTest extends DBTestCase
{
    /**
     * @test
     */
    public function itCanCreateWithNewHasOne()
    {
        factory(User::class)->create();

        $schema = '
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
        $query = '
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
        ';
        $result = $this->execute($schema, $query);
        $this->assertSame('1', Arr::get($result, 'data.createTask.id'));
        $this->assertSame('foo', Arr::get($result, 'data.createTask.name'));
        $this->assertSame('1', Arr::get($result, 'data.createTask.post.id'));
        $this->assertSame('foobar', Arr::get($result, 'data.createTask.post.body'));
    }

}