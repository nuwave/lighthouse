<?php

namespace Tests\Integration\Schema\Directives;

use Illuminate\Support\Facades\DB;
use Nuwave\Lighthouse\Exceptions\DefinitionException;
use Tests\DBTestCase;
use Tests\Utils\Models\Activity;
use Tests\Utils\Models\Image;
use Tests\Utils\Models\Post;
use Tests\Utils\Models\Task;
use Tests\Utils\Models\User;

class CountDirectiveDBTest extends DBTestCase
{
    public function testItRequiresARelationOrModelArgument(): void
    {
        $this->schema = /** @lang GraphQL */ '
        type Query {
            tasks: Int @count
        }
        ';

        $this->expectException(DefinitionException::class);
        $this->graphQL(/** @lang GraphQL */ '
        {
            tasks
        }
        ');
    }

    public function testItCanCountAModel(): void
    {
        $this->schema = /** @lang GraphQL */ '
        type Query {
            tasks_count: Int @count(model: "Task")
        }
        ';

        factory(Task::class, 3)->create();

        $this->graphQL(/** @lang GraphQL */ '
        {
            tasks_count
        }
        ')->assertExactJson([
            'data' => [
                'tasks_count' => 3,
            ],
        ]);
    }

    public function testItCanCountAModelWithScopes(): void
    {
        $this->schema = /** @lang GraphQL */ '
        type Query {
            completed_tasks: Int @count(model: "Task", scopes: ["completed"])
        }
        ';

        factory(Task::class, 3)->create();
        factory(Task::class, 2)->create([
            'completed_at' => now(),
        ]);

        $this->graphQL(/** @lang GraphQL */ '
        {
            completed_tasks
        }
        ')->assertExactJson([
            'data' => [
                'completed_tasks' => 2,
            ],
        ]);
    }

    public function testItCountsARelationAndEagerLoadsTheCount(): void
    {
        $this->schema = /** @lang GraphQL */ '
        type Query {
            users: [User!] @all
        }

        type User {
            tasks_count: Int @count(relation: "tasks")
        }
        ';

        factory(User::class, 3)->create()
            ->each(function (User $user, int $index): void {
                factory(Task::class, 3 - $index)->create([
                    'user_id' => $user->getKey(),
                ]);
            });

        $queries = 0;
        DB::listen(function ($q) use (&$queries): void {
            $queries++;
        });

        $this->graphQL(/** @lang GraphQL */ '
        {
            users {
                tasks_count
            }
        }
        ')->assertExactJson([
            'data' => [
                'users' => [
                    [
                        'tasks_count' => 3,
                    ],
                    [
                        'tasks_count' => 2,
                    ],
                    [
                        'tasks_count' => 1,
                    ],
                ],
            ],
        ]);

        $this->assertEquals(2, $queries);
    }

    public function testItCountsARelationThatIsNotSuffixedWithCount(): void
    {
        $this->schema = /** @lang GraphQL */ '
        type Query {
            user: User! @first
        }

        type User {
            tasks: Int @count(relation: "tasks")
        }
        ';

        factory(Task::class, 3)->create([
            'user_id' => factory(User::class)->create(),
        ]);

        $this->graphQL(/** @lang GraphQL */ '
        {
            user {
                tasks
            }
        }
        ')->assertExactJson([
            'data' => [
                'user' => [
                    'tasks' => 3,
                ],
            ],
        ]);
    }

    public function testItCountsARelationshipWithScopesApplied(): void
    {
        $this->schema = /** @lang GraphQL */ '
        type Query {
            user: User @first
        }

        type User {
            completed_tasks: Int! @count(relation: "tasks", scopes: ["completed"])
        }
        ';

        /** @var \Tests\Utils\Models\User $user */
        $user = factory(User::class)->create();
        factory(Task::class, 3)->create([
            'user_id' => $user->getKey(),
        ]);

        factory(Task::class)->state('completed')->create([
            'user_id' => $user->getKey(),
        ]);

        $this->graphQL(/** @lang GraphQL */ '
        {
            user {
                completed_tasks
            }
        }
        ')->assertExactJson([
            'data' => [
                'user' => [
                    'completed_tasks' => 1,
                ],
            ],
        ]);
    }

    public function testItCanCountPolymorphicRelations(): void
    {
        $this->schema = /** @lang GraphQL */ '
        type Query {
            activity: [Activity!] @all
        }

        type Post {
            id: ID
            images_count: Int @count(relation: "images")
        }

        type Task {
            id: ID
            images_count: Int @count(relation: "images")
        }

        union ActivityContent = Post | Task

        type Activity {
            id: ID
            content: ActivityContent! @morphTo
        }
        ';

        /** @var \Tests\Utils\Models\User $user */
        $user = factory(User::class)->create();

        /** @var \Tests\Utils\Models\Post $post1 */
        $post1 = factory(Post::class)->make();
        $user->posts()->save($post1);

        /** @var \Tests\Utils\Models\Activity $activity1 */
        $activity1 = factory(Activity::class)->make();
        $activity1->user()->associate($user);
        $post1->activity()->save($activity1);

        $post1->images()
            ->saveMany(
                factory(Image::class, 3)->make()
            );

        /** @var \Tests\Utils\Models\Post $post2 */
        $post2 = factory(Post::class)->make();
        $user->posts()->save($post2);

        /** @var \Tests\Utils\Models\Activity $activity2 */
        $activity2 = factory(Activity::class)->make();
        $activity2->user()->associate($user);
        $post2->activity()->save($activity2);

        $post2->images()
            ->saveMany(
                factory(Image::class, 2)->make()
            );

        $task = $post1->task;

        /** @var \Tests\Utils\Models\Activity $activity3 */
        $activity3 = factory(Activity::class)->make();
        $activity3->user()->associate($user);
        $task->activity()->save($activity3);

        $task->images()
            ->saveMany(
                factory(Image::class, 4)->make()
            );

        $this->graphQL(/** @lang GraphQL */ '
        {
            activity {
                id
                content {
                    __typename

                    ... on Post {
                        id
                        images_count
                    }

                    ... on Task {
                        id
                        images_count
                    }
                }
            }
        }
        ')->assertExactJson([
            'data' => [
                'activity' => [
                    [
                        'id' => '1',
                        'content' => [
                            '__typename' => 'Post',
                            'id' => "{$post1->id}",
                            'images_count' => $post1->images()->count(),
                        ],
                    ],
                    [
                        'id' => '2',
                        'content' => [
                            '__typename' => 'Post',
                            'id' => "{$post2->id}",
                            'images_count' => $post2->images()->count(),
                        ],
                    ],
                    [
                        'id' => '3',
                        'content' => [
                            '__typename' => 'Task',
                            'id' => "{$task->id}",
                            'images_count' => $task->images()->count(),
                        ],
                    ],
                ],
            ],
        ]);
    }

    public function testCanResolveCountByModel(): void
    {
        factory(User::class)->times(3)->create();

        $this->schema = /** @lang GraphQL */ '
        type Query {
            users: Int! @count(model: "User")
        }
        ';

        $this->graphQL(/** @lang GraphQL */ '
        {
            users
        }
        ')->assertJson([
            'data' => [
                'users' => 3,
            ],
        ]);
    }

    public function testCanResolveCountByRelation(): void
    {
        /** @var User $user */
        $user = factory(User::class)->create();

        $user->tasks()->saveMany(
            factory(Task::class)->times(4)->create()
        );

        $this->be($user);

        $this->schema = /** @lang GraphQL */ '
        type User {
            taskCount: Int! @count(relation: "tasks")
        }

        type Query {
            user: User @auth
        }
        ';

        $this->graphQL(/** @lang GraphQL */ '
        {
            user {
                taskCount
            }
        }
        ')->assertJson([
            'data' => [
                'user' => [
                    'taskCount' => 4,
                ],
            ],
        ]);
    }
}
