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

        $this->schema = '
        type User {
            id: Int!
        }
        
        type Query {
            me: User!
                @inject(context: "user.id", name: "user_id")
                @field(resolver: "'.$this->qualifyTestResolver('resolveUser').'")
        }
        ';

        $this->graphQL('
        {
            me {
                id
            }
        }
        ')->assertJson([
            'data' => [
                'me' => [
                    'id' => 1,
                ],
            ],
        ]);
    }

    public function resolveUser($root, array $args): array
    {
        return [
            'id' => $args['user_id'],
        ];
    }
}
