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

        $schema = $this->buildSchemaFromString('
        type User {
            foo: String!
        }
        
        type Query {
            user: User! @inject(context: "user.id", name: "user_id") @field(resolver: "' . addslashes(self::class) . '@resolveUser")
        }
        ');

        $query = '
        {
            user {
                foo
            }
        }
        ';

        $this->postJson('graphql',['query' => $query]);
    }

    public function resolveUser($root, $args)
    {
        $this->assertSame(1, $args['user_id']);
    }
}
