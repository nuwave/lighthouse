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
                limit: Int @builder(method: "'.addslashes(self::class).'@limit")
            ): [User!]! @all
        }
        
        type User {
            id: ID
        }
        ';

        factory(User::class, 2)->create();

        $this->query('
        {
            users(limit: 1) {
                id
            }
        }
        ')->assertJsonCount(1, 'data.users');
    }

    public function limit($builder, int $value)
    {
        return $builder->limit($value);
    }
}
