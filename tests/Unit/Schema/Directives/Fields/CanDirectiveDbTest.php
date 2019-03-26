<?php

namespace Tests\Unit\Schema\Directives\Fields;

use Tests\DBTestCase;
use Tests\Utils\Models\Post;
use Tests\Utils\Models\User;
use Nuwave\Lighthouse\Exceptions\AuthorizationException;

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
            'name' => 'admin',
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

    /**
     * @test
     * @dataProvider provideAcceptableArgumentNames
     *
     * @param  string  $argumentName
     */
    public function itThrowsIfNotAuthorized(string $argumentName): void
    {
        $user = User::create([
            'name' => 'admin',
        ]);
        $this->be($user);

        $userB = User::create([
            'name' => 'foo',
        ]);

        $postB = factory(Post::class)->create([
            'user_id' => $userB->getKey(),
            'title' => 'Harry Potter and the Half-Blood Prince',
        ]);

        $this->schema = '
        type Query {
            post(id: ID @eq): Post
                @can('.$argumentName.': "view")
                @field(resolver: "'.addslashes(self::class).'@resolvePost")
        }
        
        type Post {
            id: ID!
            title: String!
        }
        ';

        $this->query("
        {
            post(id: {$postB->getKey()}) {
                title
            }
        }
        ")->assertErrorCategory(AuthorizationException::CATEGORY);
    }

    public function resolveUser($root, array $args)
    {
        return User::where('id', $args['id'])->first();
    }

    public function resolvePost($root, array $args)
    {
        return Post::where('id', $args['id'])->first();
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
