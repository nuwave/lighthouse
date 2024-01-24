<?php declare(strict_types=1);

namespace Tests\Integration\Schema\Directives;

use Nuwave\Lighthouse\Exceptions\DefinitionException;
use Tests\DBTestCase;
use Tests\Utils\Models\Tag;
use Tests\Utils\Models\Task;
use Tests\Utils\Models\User;

final class ScopeDirectiveTest extends DBTestCase
{
    public function testExplicitName(): void
    {
        factory(Task::class)->times(2)->create();

        /** @var Task $taskWithTag */
        $taskWithTag = factory(Task::class)->create();

        /** @var Tag $tag */
        $tag = factory(Tag::class)->make();
        $taskWithTag->tags()->save($tag);

        $this->schema = /** @lang GraphQL */ '
        type Query {
            tasks(tags: [String!] @scope(name: "whereTags")): [Task!]! @all
        }

        type Task {
            id: ID!
        }
        ';

        $this->graphQL(/** @lang GraphQL */ '
        {
            tasks {
                id
            }
        }
        ')->assertJsonCount(3, 'data.tasks');

        $this->graphQL(/** @lang GraphQL */ '
        query ($tags: [String!]!) {
            tasks(tags: $tags) {
                id
            }
        }
        ', [
            'tags' => [$tag->name],
        ])->assertExactJson([
            'data' => [
                'tasks' => [
                    [
                        'id' => "{$taskWithTag->id}",
                    ],
                ],
            ],
        ]);
    }

    public function testDefaultsScopeToEqualArgumentName(): void
    {
        factory(Task::class)->times(2)->create();

        /** @var Task $taskWithTag */
        $taskWithTag = factory(Task::class)->create();

        /** @var Tag $tag */
        $tag = factory(Tag::class)->make();
        $taskWithTag->tags()->save($tag);

        $this->schema = /** @lang GraphQL */ '
        type Query {
            tasks(whereTags: [String!] @scope): [Task!]! @all
        }

        type Task {
            id: ID!
        }
        ';

        $this->graphQL(/** @lang GraphQL */ '
        query ($whereTags: [String!]!) {
            tasks(whereTags: $whereTags) {
                id
            }
        }
        ', [
            'whereTags' => [$tag->name],
        ])->assertExactJson([
            'data' => [
                'tasks' => [
                    [
                        'id' => "{$taskWithTag->id}",
                    ],
                ],
            ],
        ]);
    }

    public function testWorksWithCustomQueryBuilders(): void
    {
        $named = factory(User::class)->make();
        assert($named instanceof User);
        $named->name = 'foo';
        $named->save();

        $unnamed = factory(User::class)->make();
        assert($unnamed instanceof User);
        $unnamed->name = null;
        $unnamed->save();

        $this->schema = /** @lang GraphQL */ '
        type Query {
            users(named: Boolean @scope): [User!]! @all
        }

        type User {
            id: ID!
        }
        ';

        $this->graphQL(/** @lang GraphQL */ '
        query ($named: Boolean) {
            users(named: $named) {
                id
            }
        }
        ', [
            'named' => true,
        ])->assertExactJson([
            'data' => [
                'users' => [
                    [
                        'id' => "{$named->id}",
                    ],
                ],
            ],
        ]);

        $this->graphQL(/** @lang GraphQL */ '
        query {
            users {
                id
            }
        }
        ', [
            'named' => false,
        ])->assertSimilarJson([
            'data' => [
                'users' => [
                    [
                        'id' => "{$named->id}",
                    ],
                    [
                        'id' => "{$unnamed->id}",
                    ],
                ],
            ],
        ]);
    }

    public function testThrowExceptionOnInvalidScope(): void
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
