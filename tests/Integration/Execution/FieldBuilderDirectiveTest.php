<?php declare(strict_types=1);

namespace Tests\Integration\Execution;

use Illuminate\Contracts\Auth\Factory as AuthFactory;
use Tests\DBTestCase;
use Tests\Utils\Models\Post;
use Tests\Utils\Models\User;

final class FieldBuilderDirectiveTest extends DBTestCase
{
    protected string $schema = /** @lang GraphQL */ '
    type Post {
        id: Int!
    }
    ';

    public function testLimitPostByAuthenticatedUser(): void
    {
        $this->schema .= /** @lang GraphQL */ '
        type Query {
            posts: [Post!]!
                @all
                @whereAuth(relation: "user")
        }
        ';

        $user = factory(User::class)->create();
        $this->assertInstanceOf(User::class, $user);
        $ownedPosts = factory(Post::class, 3)->create();
        $ownedPosts->each(static function (Post $post) use ($user): void {
            $post->user()->associate($user);
            $post->save();
        });
        factory(Post::class, 3)->create();

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
            $response->json('data.posts.*.id'),
        );
    }

    public function testChangeGuard(): void
    {
        $this->schema .= /** @lang GraphQL */ '
        type Query {
            posts: [Post!]!
                @all
                @whereAuth(
                    relation: "user"
                    guards: ["web"]
                )
        }
        ';
        $user = factory(User::class)->create();
        $this->assertInstanceOf(User::class, $user);
        $ownedPosts = factory(Post::class, 3)->create();
        $ownedPosts->each(static function (Post $post) use ($user): void {
            $post->user()->associate($user);
            $post->save();
        });
        factory(Post::class, 3)->create();

        $authFactory = $this->app->make(AuthFactory::class);
        $authFactory->guard('web')->setUser($user);

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
            $response->json('data.posts.*.id'),
        );
    }
}
