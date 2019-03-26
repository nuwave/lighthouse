<?php

namespace Tests\Unit\Schema\Directives\Fields;

use Tests\DBTestCase;
use Tests\Utils\Models\User;

class CanDirectiveDbTest extends DBTestCase
{
    /**
     * @test
     * @dataProvider provideAcceptableArgumentNames
     *
     * @param  string  $argumentName
     */
    public function itPassesIfModelInstanceIsNotNull(string $argumentName): void
    {
        $user = User::create([
            'name' => 'admin'
        ]);
        $this->be($user);

        $user = factory(User::class)->create(['name' => 'foo']);

        $this->schema = '
        type Query {
            user(id: ID @eq): User
                @can('.$argumentName.': "view")
                @field(resolver: "'.addslashes(self::class).'@resolveUser")
        }
        
        type User {
            id: ID!
            name: String!
        }
        ';

        $this->query("
        {
            user(id: {$user->getKey()}) {
                name
            }
        }
        ")->assertJson([
            'data' => [
                'user' => [
                    'name' => 'foo',
                ],
            ],
        ]);
    }

    public function resolveUser($root, array $args)
    {
        return User::where('id', $args['id'])->first();
    }

    /**
     * @return array[]
     */
    public function provideAcceptableArgumentNames(): array
    {
        return [
            ['if'],
            ['ability'],
        ];
    }
}
