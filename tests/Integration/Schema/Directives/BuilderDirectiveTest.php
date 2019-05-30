<?php

namespace Tests\Integration\Schema\Directives;

use Tests\DBTestCase;
use Tests\Utils\Models\User;

class BuilderDirectiveTest extends DBTestCase
{
    /**
     * @test
     */
    public function itCallsCustomBuilderMethod(): void
    {
        $this->schema = '
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

        $this->graphQL('
        {
            users(limit: 1) {
                id
            }
        }
        ')->assertJsonCount(1, 'data.users');
    }

    /**
     * @param  \Illuminate\Database\Query\Builder|\Illuminate\Database\Eloquent\Builder  $builder
     * @param  int  $value
     * @return \Illuminate\Database\Query\Builder|\Illuminate\Database\Eloquent\Builder
     */
    public function limit($builder, int $value)
    {
        return $builder->limit($value);
    }
}
