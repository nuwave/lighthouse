<?php declare(strict_types=1);

namespace Tests\Integration\Execution;

use Illuminate\Contracts\Auth\Factory as AuthFactory;
use Tests\DBTestCase;
use Tests\Utils\Models\Post;
use Tests\Utils\Models\User;

final class FieldBuilderDirectiveTest extends DBTestCase
{
    protected string $schema = /** @lang GraphQL */ <<<'GRAPHQL'
    type Post {
        id: Int!
    }
    GRAPHQL;

    public function testLimitPostByAuthenticatedUser(): void
    {
        $this->schema .= /** @lang GraphQL */ <<<'GRAPHQL'
        type Query {
            posts: [Post!]!
                @all
                @whereAuth(relation: "user")
        }
        GRAPHQL;

        $user = factory(User::class)->create();
        $this->assertInstanceOf(User::class, $user);
        $ownedPosts = factory(Post::class, 3)->make();
        $ownedPosts->each(static function (Post $post) use ($user): void {
            $post->user()->associate($user);
            $post->save();
        });
        factory(Post::class, 3)->create();

        $this->be($user);

        $response = $this->graphQL(/** @lang GraphQL */ <<<'GRAPHQL'
        query {
            posts {
                id
            }
        }
        GRAPHQL);

        $this->assertSame(
            $ownedPosts->pluck('id')->all(),
            $response->json('data.posts.*.id'),
        );
    }

    public function testChangeGuard(): void
    {
        $this->schema .= /** @lang GraphQL */ <<<'GRAPHQL'
        type Query {
            posts: [Post!]!
                @all
                @whereAuth(
                    relation: "user"
                    guards: ["web"]
                )
        }
        GRAPHQL;
        $user = factory(User::class)->create();
        $this->assertInstanceOf(User::class, $user);
        $ownedPosts = factory(Post::class, 3)->make();
        $ownedPosts->each(static function (Post $post) use ($user): void {
            $post->user()->associate($user);
            $post->save();
        });
        factory(Post::class, 3)->create();

        $authFactory = $this->app->make(AuthFactory::class);
        $authFactory->guard('web')->setUser($user);

        $response = $this
            ->graphQL(/** @lang GraphQL */ <<<'GRAPHQL'
            query {
                posts {
                    id
                }
            }
            GRAPHQL);

        $this->assertSame(
            $ownedPosts->pluck('id')->all(),
            $response->json('data.posts.*.id'),
        );
    }
}
