<?php

namespace Tests\Integration\Schema\Directives;

use Illuminate\Support\Facades\DB;
use Nuwave\Lighthouse\Exceptions\DefinitionException;
use Tests\DBTestCase;
use Tests\Utils\Models\Post;
use Tests\Utils\Models\Comment;

class WithSumDirectiveTest extends DBTestCase
{
    public function testEagerLoadsRelationSum(): void
    {
        $this->schema = /** @lang GraphQL */ '
        type Query {
            posts: [Post!] @all
        }

        type Post {
            comments_sum_votes: Int!
                @withSum(relation: "comments", column: "votes")
        }
        ';

        factory(Post::class, 3)->create()
           ->each(function (Post $post, int $index): void {
               factory(Comment::class)
                   ->create([
                       'post_id' => $post->getKey(),
                       'votes' => (3 - $index)
                   ]);
           });

        $queries = 0;
        DB::listen(function ($q) use (&$queries): void {
            $queries++;
        });

      $this->graphQL(/** @lang GraphQL */ '
        {
            posts {
                comments_sum_votes
            }
        }
        ')->assertExactJson([
            'data' => [
                'posts' => [
                    [
                        'comments_sum_votes' => 3,
                    ],
                    [
                        'comments_sum_votes' => 2,
                    ],
                    [
                        'comments_sum_votes' => 1,
                    ],
                ],
            ],
        ]);

        $this->assertSame(2, $queries);
    }

    public function _testFailsToEagerLoadRelationSumWithoutRelation(): void
    {
        $this->withExceptionHandling();
        $this->schema = /** @lang GraphQL */ '
        type Query {
            posts: [Post!] @all
        }

        type Post {
            comments_sum_votes: Int!
                @withSum(relation: "comments", column: "votes")
        }
        ';

        factory(Post::class)->create();

        $this->expectException(DefinitionException::class);
        $this->graphQL(/** @lang GraphQL */ '
        {
            posts {
                title
            }
        }
        ');
    }
}
