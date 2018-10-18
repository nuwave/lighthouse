<?php

namespace Tests\Unit\Schema\Directives\Fields;

use Tests\DBTestCase;
use Tests\Utils\Models\User;

class InjectDirectiveTest extends DBTestCase
{
    /**
     * @test
     */
    public function itCanInjectDataFromContextIntoArgs()
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
                @field(resolver: "' . addslashes(self::class) . '@resolveUser")
        }
        ';
        $query = '
        {
            me {
                id
            }
        }
        ';

        $result = $this->postJson('graphql',['query' => $query])->json();

        $this->assertSame(1, array_get($result, 'data.me.id'));
    }

    public function resolveUser($root, array $args): array
    {
        return [
            'id' => $args['user_id']
        ];
    }
}
