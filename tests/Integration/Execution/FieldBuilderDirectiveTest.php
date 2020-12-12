<?php

namespace Tests\Integration\Execution;

use Tests\DBTestCase;
use Tests\Utils\Models\Post;
use Tests\Utils\Models\User;

class FieldBuilderDirectiveTest extends DBTestCase
{
    protected $schema = /** @lang GraphQL */ '
    type Post {
        id: Int!
        title: String
    }
    ';

    public function testCanLimitPostByAuthenticatedUser(): void
    {
        $this->schema .= /** @lang GraphQL */ '
        type Query {
            posts: [Post!]! @all @whereAuth(relation: "user")
        }
        ';
        $user = factory(User::class)->create();
        $ownedPosts = factory(Post::class, 3)->create([
            'user_id' => $user->getKey(),
        ]);
        $nonOwnedPosts = factory(Post::class, 3)->create();

        $response = $this
            ->actingAs($user)
            ->graphQL(/** @lang GraphQL */ '
            query {
                posts {
                    id
                }
            }
            ');

        $this->assertSame(
            $ownedPosts->pluck('id')->all(),
            $response->json('data.posts.*.id')
        );
    }

    public function testCanChangeGuard(): void
    {
        $this->schema .= /** @lang GraphQL */ '
        type Query {
            posts: [Post!]! @all @whereAuth(
                relation: "user"
                guard: "api"
            )
        }
        ';
        $user = factory(User::class)->create();
        $ownedPosts = factory(Post::class, 3)->create([
            'user_id' => $user->getKey(),
        ]);
        $nonOwnedPosts = factory(Post::class, 3)->create();

        $this->app['auth']->guard('api')->setUser($user);

        $response = $this
            ->graphQL(/** @lang GraphQL */ '
            query {
                posts {
                    id
                }
            }
            ');

        $this->assertSame(
            $ownedPosts->pluck('id')->all(),
            $response->json('data.posts.*.id')
        );
    }
}
