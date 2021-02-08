<?php

namespace Tests\Integration\Schema\Directives;

use Tests\DBTestCase;
use Tests\Utils\Models\User;

class BuilderDirectiveTest extends DBTestCase
{
    public function testCallsCustomBuilderMethod(): void
    {
        $this->schema = /** @lang GraphQL */ '
        type Query {
            users(
                limit: Int @builder(method: "'.$this->qualifyTestResolver('limit').'")
            ): [User!]! @all
        }

        type User {
            id: ID
        }
        ';

        factory(User::class, 2)->create();

        $this->graphQL(/** @lang GraphQL */ '
        {
            users(limit: 1) {
                id
            }
        }
        ')->assertJsonCount(1, 'data.users');
    }

    public function testCallsCustomBuilderMethodOnFieldWithValue(): void
    {
        $this->schema = /** @lang GraphQL */ '
        type Query {
            users: [User!]! @all @builder(method: "'.$this->qualifyTestResolver('limit').'" value: 1)
        }

        type User {
            id: ID
        }
        ';

        factory(User::class, 2)->create();

        $this->graphQL(/** @lang GraphQL */ '
        {
            users {
                id
            }
        }
        ')->assertJsonCount(1, 'data.users');
    }

    public function testCallsCustomBuilderMethodOnFieldWithoutValue(): void
    {
        $this->schema = /** @lang GraphQL */ '
        type Query {
            users: [User!]! @all @builder(method: "'.$this->qualifyTestResolver('limit').'")
        }

        type User {
            id: ID
        }
        ';

        factory(User::class, 3)->create();

        $this->graphQL(/** @lang GraphQL */ '
        {
            users {
                id
            }
        }
        ')->assertJsonCount(2, 'data.users');
    }

    /**
     * @param  \Illuminate\Database\Query\Builder|\Illuminate\Database\Eloquent\Builder  $builder
     * @return \Illuminate\Database\Query\Builder|\Illuminate\Database\Eloquent\Builder
     */
    public function limit(object $builder, int $value = 2): object
    {
        return $builder->limit($value);
    }
}
