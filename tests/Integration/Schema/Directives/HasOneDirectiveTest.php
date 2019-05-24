<?php

namespace Tests\Integration\Schema\Directives;

use Tests\DBTestCase;
use Tests\Utils\Models\Post;
use Tests\Utils\Models\Task;

class HasOneDirectiveTest extends DBTestCase
{
    /**
     * @test
     */
    public function itCanQueryHasOneRelationship(): void
    {
        // Task with id 1, no post
        factory(Task::class)->create();
        // Creates a task with id 2 and assigns it to this post
        factory(Post::class)->create();

        $this->schema = '
        type Post {
            id: Int
        }
        
        type Task {
            post: Post @hasOne
        }
        
        type Query {
            tasks: [Task!]! @all
        }
        ';

        $this->graphQL('
        {
            tasks {
                post {
                    id
                }
            }
        }
        ')->assertJson([
            'data' => [
                'tasks' => [
                    [
                        'post' => null,
                    ],
                    [
                        'post' => [
                            'id' => 1,
                        ],
                    ],
                ],
            ],
        ]);
    }
}
