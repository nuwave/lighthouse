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

    /**
     * @test
     */
    public function itCanCreateQueryPaginatorsInGroup()
    {
        factory(User::class, 2)->create();

        $schema = '
        type User {
            id: ID!
            name: String!
        }

        type Query {
            dummy: Int
        }

        extend type Query @group {
            users: [User!]! @paginate(model: "User")
        }
        ';

        $query = '
        {
            users(count: 1) {
                data {
                    id
                    name
                }
            }
        }
        ';

        $result = $this->executeQuery($schema, $query);


        $this->assertCount(1, array_get($result->data, 'users.data'));
    }

    public function resolveUser($root, $args)
    {
        $this->assertSame(1, $args['user_id']);
    }

}
