<?php

namespace Tests\Integration\Schema\Directives;

use Nuwave\Lighthouse\Exceptions\DefinitionException;
use Tests\DBTestCase;
use Tests\Utils\Models\Tag;
use Tests\Utils\Models\Task;

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
                        'id' => "$taskWithTag->id",
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
                        'id' => "$taskWithTag->id",
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
