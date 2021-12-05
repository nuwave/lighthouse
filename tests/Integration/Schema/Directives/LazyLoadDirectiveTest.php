<?php

namespace Tests\Integration\Schema\Directives;

use Nuwave\Lighthouse\Exceptions\DefinitionException;
use Tests\DBTestCase;
use Tests\Utils\Models\Task;
use Tests\Utils\Models\User;

class LazyLoadDirectiveTest extends DBTestCase
{
    public function testLazyLoadRequiresRelationArgument(): void
    {
        $this->expectException(DefinitionException::class);

        $this->buildSchema(/** @lang GraphQL */ '
        type Query {
            foo: ID @lazyLoad
        }
        ');
    }

    public function testLazyLoadRelationArgumentMustNotBeEmptyList(): void
    {
        $this->expectException(DefinitionException::class);

        $this->buildSchema(/** @lang GraphQL */ '
        type Query {
            foo: ID @lazyLoad(relations: [])
        }
        ');
    }

    public function testLazyLoadRelationsOnConnections(): void
    {
        /** @var \Tests\Utils\Models\User $user */
        $user = factory(User::class)->create();

        $user->tasks()->saveMany(factory(Task::class, 3)->make());

        $this->schema = /** @lang GraphQL */ '
        type User {
            tasks: [Task!]!
                @lazyLoad(relations: ["user"])
                @hasMany(type: CONNECTION)
        }

        type Task {
            id: ID!
            user: User!
        }

        type Query {
            user: User @first
        }
        ';

        $this->graphQL(/** @lang GraphQL */ '
        {
            user {
                tasks(first: 3) {
                    edges {
                        node {
                            id
                        }
                    }
                }
            }
        }
        ');
    }
}
