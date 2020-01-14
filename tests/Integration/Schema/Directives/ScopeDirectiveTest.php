<?php

namespace Tests\Integration\Schema\Directives;

use Nuwave\Lighthouse\Exceptions\DefinitionException;
use Tests\DBTestCase;
use Tests\Utils\Models\Tag;
use Tests\Utils\Models\Task;
use Tests\Utils\Models\User;

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

        $this->schema = /** @lang GraphQL */ '
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

        $this->graphQL(/** @lang GraphQL */ '
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
        $this->schema = /** @lang GraphQL */ '
        type Query {
            tasks(
                name: String @scope(name: "nonExistantScope")
            ): [Task!]! @all
        }
        
        type Task {
            id: ID
        }
        ';

        $this->expectException(DefinitionException::class);
        $this->graphQL(/** @lang GraphQL */ '
        {
            tasks(name: "Lighthouse rocks") {
                id
            }
        }
        ');
    }
}
