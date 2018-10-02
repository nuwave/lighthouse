<?php

namespace Tests\Integration\Schema\Directives\Fields;

use Tests\DBTestCase;
use Tests\Utils\Models\Post;
use Tests\Utils\Models\Task;

class HasOneDirectiveTest extends DBTestCase
{
    /**
     * @test
     */
    public function itCanQueryHasOneRelationship()
    {
        // Task with id 1, no post
        factory(Task::class)->create();
        // Creates a task with id 2 and assigns it to this post
        factory(Post::class)->create();

        $schema = '
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

        $result = $this->execute($schema, '
        {
            tasks {
                post {
                    id
                }
            }
        }
        ');

        $this->assertSame(
            [
                'tasks' => [
                    ['post' => null],
                    ['post' => ['id' => 1]],
                ],
            ],
            $result['data']
        );
    }
}
