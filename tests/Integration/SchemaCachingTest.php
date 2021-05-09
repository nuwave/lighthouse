<?php

namespace Tests\Integration;

use Illuminate\Contracts\Config\Repository as ConfigRepository;
use Nuwave\Lighthouse\Schema\AST\ASTBuilder;
use function Safe\unlink;
use Tests\TestCase;
use Tests\TestsSerialization;
use Tests\Utils\Models\Comment;

class SchemaCachingTest extends TestCase
{
    use TestsSerialization;

    /**
     * @var string
     */
    private $cachePath;

    protected function getEnvironmentSetUp($app): void
    {
        parent::getEnvironmentSetUp($app);

        /** @var \Illuminate\Contracts\Config\Repository $config */
        $config = $app->make(ConfigRepository::class);
        $config->set('lighthouse.cache.enable', true);
        $this->cachePath = __DIR__.'/../storage/'.__METHOD__.'.php';
        $config->set('lighthouse.cache.path', $this->cachePath);

        $this->useSerializingArrayStore($app);
    }

    protected function tearDown(): void
    {
        unlink($this->cachePath);

        parent::tearDown();
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
