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

    /**
     * @param  \Illuminate\Database\Query\Builder|\Illuminate\Database\Eloquent\Builder  $builder
     * @return \Illuminate\Database\Query\Builder|\Illuminate\Database\Eloquent\Builder
     */
    public function limit(object $builder, int $value): object
    {
        return $builder->limit($value);
    }
}
