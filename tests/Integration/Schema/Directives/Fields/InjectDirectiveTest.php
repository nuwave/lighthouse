<?php

namespace Tests\Integration\Schema\Directives\Fields;

use Tests\DBTestCase;
use Illuminate\Support\Arr;
use Tests\Utils\Models\User;

class InjectDirectiveTest extends DBTestCase
{
    /**
     * @test
     */
    public function itCanCreateFromInputObjectWithDeepInjection()
    {
        $user = factory(User::class)->create();
        $this->be($user);

        $schema = '
        type Task {
            id: ID!
            name: String!
            user: User @belongsTo
        }
        
        type User {
            id: ID
        }
        
        type Mutation {
            createTask(input: CreateTaskInput!): Task @create(flatten: true) @inject(context: "user.id", name: "input.user_id")
        }
        
        input CreateTaskInput {
            name: String
        }
        ' . $this->placeholderQuery();
        $query = '
        mutation {
            createTask(input: {
                name: "foo"
            }) {
                id
                name
                user {
                    id
                }
            }
        }
        ';
        $this->schema = $schema;
        $result = $this->queryViaHttp($query);

        $this->assertSame('1', Arr::get($result, 'data.createTask.id'));
        $this->assertSame('foo', Arr::get($result, 'data.createTask.name'));
        $this->assertSame('1', Arr::get($result, 'data.createTask.user.id'));
    }


}
