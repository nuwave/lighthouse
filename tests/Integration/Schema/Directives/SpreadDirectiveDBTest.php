<?php

namespace Tests\Integration\Schema\Directives;

use Tests\DBTestCase;
use Tests\Utils\Models\User;

class SpreadDirectiveDBTest extends DBTestCase
{
    public function testSpreadsTheInputIntoTheQuery(): void
    {
        factory(User::class, 2)->create();

        $this->schema = '
        type Query {
            user(input: UserInput @spread): User @first
        }

        type User {
            id: ID
        }

        input UserInput {
            id: ID @eq
        }
        ';

        $this->graphQL('
        {
            user(input: {
                id: 2
            }) {
                id
            }
        }
        ')->assertJson([
            'data' => [
                'user' => [
                    'id' => 2,
                ],
            ],
        ]);
    }

    public function testIgnoresSpreadedInputIfNotGiven(): void
    {
        factory(User::class)->create();

        $this->schema = '
        type Query {
            user(input: UserInput @spread): User @first
        }

        type User {
            id: ID
        }

        input UserInput {
            id: ID @eq
        }
        ';

        $this->graphQL('
        {
            user {
                id
            }
        }
        ')->assertJson([
            'data' => [
                'user' => [
                    'id' => 1,
                ],
            ],
        ]);
    }
}
