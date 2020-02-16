<?php

namespace Tests\Integration\Schema\Directives;

use Tests\DBTestCase;
use Tests\Utils\Models\User;

class InjectDirectiveDBTest extends DBTestCase
{
    public function testCanInjectDataFromContextIntoArgs(): void
    {
        $user = factory(User::class)->create();
        $this->be($user);

        $this->mockResolver()
            ->with(null, ['user_id' => 1]);

        $this->schema = /** @lang GraphQL */ '
        type User {
            id: Int!
        }

        type Query {
            me: User
                @inject(context: "user.id", name: "user_id")
                @mock
        }
        ';

        $this->graphQL(/** @lang GraphQL */ '
        {
            me {
                id
            }
        }
        ');
    }
}
