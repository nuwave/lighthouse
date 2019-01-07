<?php

namespace Tests\Unit\Schema\Directives\Fields;

use Tests\DBTestCase;
use Tests\Utils\Models\User;

class InjectDirectiveTest extends DBTestCase
{
    /**
     * @test
     */
    public function itCanInjectDataFromContextIntoArgs(): void
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
                @field(resolver: "'.addslashes(self::class).'@resolveUser")
        }
        ';

        $this->query('
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
