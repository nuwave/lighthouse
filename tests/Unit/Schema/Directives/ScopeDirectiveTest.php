<?php

namespace Tests\Unit\Schema\Directives;

use Tests\DBTestCase;
use Tests\Utils\Models\Tag;
use Tests\Utils\Models\Task;
use Tests\Utils\Models\User;
use Nuwave\Lighthouse\Exceptions\DirectiveException;

class ScopeDirectiveTest extends DBTestCase
{
    public function testCanApplyDirective(): void
    {
        /** @var User $user */
        $user = factory(User::class)->create();

        $user->tasks()->saveMany(
            factory(Task::class)->times(2)->create()
        );

        /** @var Task $taskWithTag */
        $taskWithTag = factory(Task::class)->create();
        $taskWithTag->tags()->save(
            factory(Tag::class)->create(['name' => 'Lighthouse'])
        );
        $user->tasks()->save($taskWithTag);

        $this->be($user);

        $this->schema = /* @lang GraphQL */
            '
        type User {
            tasks(tags: [String!] @scope(name: "whereTags")): [Task!]! @hasMany
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

        type Query {
            user: User @auth
        }
        ';

        $this->graphQL(/* @lang GraphQL */ '
        {
            user {
                tasks(tags: ["Lighthouse"]) {
                    id
                }
            }
        }
        ')->assertJson([
            'data' => [
                'user' => [
                    'tasks' => [
                        [
                            'id' => $taskWithTag->getKey(),
                        ],
                    ],
                ],
            ],
        ]);
    }

    public function testCanThrowExceptionOnInvalidScope(): void
    {
        /** @var User $user */
        $user = factory(User::class)->create();

        $user->tasks()->saveMany(
            factory(Task::class)->times(2)->create()
        );

        $this->be($user);

        $this->schema = /* @lang GraphQL */
            '
        type User {
            tasks(tags: [String!] @scope(name: "onlyTags")): [Task!]! @hasMany
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

        type Query {
            user: User @auth
        }
        ';

        $this->expectException(DirectiveException::class);
        $this->graphQL(/* @lang GraphQL */ '
        {
            user {
                tasks(tags: ["Lighthouse"]) {
                    id
                }
            }
        }
        ');
    }
}
