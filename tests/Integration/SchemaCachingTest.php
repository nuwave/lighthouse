<?php

namespace Tests\Integration;

use Illuminate\Contracts\Config\Repository as ConfigRepository;
use Nuwave\Lighthouse\Schema\AST\ASTBuilder;
use Tests\TestCase;
use Tests\TestsSchemaCache;
use Tests\TestsSerialization;
use Tests\Utils\Models\Comment;

class SchemaCachingTest extends TestCase
{
    use TestsSerialization;
    use TestsSchemaCache;

    public function setUp(): void
    {
        parent::setUp();

        $this->setUpSchemaCache();
        $this->useSerializingArrayStore($this->app);
    }

    protected function tearDown(): void
    {
        $this->tearDownSchemaCache();

        parent::tearDown();
    }

    /**
     * @dataProvider cacheVersions
     */
    public function testSchemaCachingWithUnionType(int $cacheVersion): void
    {
        /** @var \Illuminate\Contracts\Config\Repository $config */
        $config = app(ConfigRepository::class);
        $config->set('lighthouse.cache.version', $cacheVersion);

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
        $astBuilder = $this->app->make(ASTBuilder::class);
        $astBuilder->documentAST();

        $this->app->forgetInstance(ASTBuilder::class);
    }
}
