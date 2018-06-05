<?php


namespace Tests\Integration\Directives\Fields;


use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\DBTestCase;
use Tests\Utils\Models\User;

class AuthDirectiveTest extends DBTestCase
{
    use RefreshDatabase;

    /** @test */
    public function itCanResolveQuery()
    {
        /** @var User $user */
        $user = factory(User::class)->create([
            'name' => 'Oliver',
            'email' => 'olivernybroe@gmail.com'
        ]);

        $schema = '
        type User {
            id: ID!
            name: String!
            email: String!
        }
        type Query {
            me: User @auth
        }
        ';

        $query = '
        {
            me {
                name,
                email
            }
        }
        ';

        $this->be($user);
        graphql()->build($schema);
        $data = graphql()->execute($query);
        dd($data);
        $expected = [
            'data' => [
                'me' => [
                    'email' => 'olivernybroe@gmail.com',
                    'name' => 'Oliver',
                ],
            ],
        ];

        $this->assertEquals($expected, $data);
    }
}