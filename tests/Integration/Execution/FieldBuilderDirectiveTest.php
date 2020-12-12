<?php

namespace Tests\Integration\Execution;

use Illuminate\Contracts\Auth\Factory as AuthFactory;
use Tests\DBTestCase;
use Tests\Utils\Models\Post;
use Tests\Utils\Models\User;

class FieldBuilderDirectiveTest extends DBTestCase
{
    protected $schema = /** @lang GraphQL */ '
    type Post {
        id: Int!
    }
    ';

    public function testCanLimitPostByAuthenticatedUser(): void
    {
        $this->schema .= /** @lang GraphQL */ '
        type Query {
            posts: [Post!]!
                @all
                @whereAuth(relation: "user")
        }
        ';

        $user = factory(User::class)->create();
        $ownedPosts = factory(Post::class, 3)->create([
            'user_id' => $user->getKey(),
        ]);
        $nonOwnedPosts = factory(Post::class, 3)->create();

        $this->be($user);

        $response = $this->graphQL(/** @lang GraphQL */ '
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
            posts: [Post!]!
                @all
                @whereAuth(
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

        $authFactory = $this->app->make(AuthFactory::class);
        $authFactory->guard('api')->setUser($user);

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
