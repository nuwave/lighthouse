<?php

namespace Tests\Integration;

use Nuwave\Lighthouse\Schema\AST\ASTBuilder;
use Tests\TestCase;
use Tests\TestsSerialization;
use Tests\Utils\Models\Comment;

class SchemaCachingTest extends TestCase
{
    use TestsSerialization;

    protected function getEnvironmentSetUp($app): void
    {
        parent::getEnvironmentSetUp($app);

        /** @var \Illuminate\Contracts\Config\Repository $config */
        $config = $app['config'];
        $config->set('lighthouse.cache.enable', true);

        $this->useSerializingArrayStore($app);
    }

    public function testSchemaCachingWithUnionType(): void
    {
        $this->schema = /** @lang GraphQL */ '
        type Query {
            foo: Foo @mock
        }

        union Foo = Comment | Color

        type Comment {
            comment: ID
        }

        type Color {
            id: ID
        }
        ';
        $this->cacheSchema();

        $comment = new Comment();
        $comment->comment = 'foo';
        $this->mockResolver($comment);

        $this->graphQL(/** @lang GraphQL */ '
        {
            foo {
                ... on Comment {
                    comment
                }
            }
        }
        ')->assertExactJson([
            'data' => [
                'foo' => [
                    'comment' => $comment->comment,
                ],
            ],
        ]);
    }

    protected function cacheSchema(): void
    {
        /** @var \Nuwave\Lighthouse\Schema\AST\ASTBuilder $astBuilder */
        $astBuilder = app(ASTBuilder::class);
        $astBuilder->documentAST();
        $this->app->forgetInstance(ASTBuilder::class);
    }
}
