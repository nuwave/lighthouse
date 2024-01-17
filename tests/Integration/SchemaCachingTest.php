<?php declare(strict_types=1);

namespace Tests\Integration;

use Illuminate\Contracts\Config\Repository as ConfigRepository;
use Illuminate\Filesystem\Filesystem;
use Nuwave\Lighthouse\Exceptions\InvalidSchemaCacheContentsException;
use Nuwave\Lighthouse\Schema\AST\ASTBuilder;
use Tests\TestCase;
use Tests\TestsSchemaCache;
use Tests\TestsSerialization;
use Tests\Utils\Models\Comment;

final class SchemaCachingTest extends TestCase
{
    use TestsSerialization;
    use TestsSchemaCache;

    protected function setUp(): void
    {
        parent::setUp();

        $this->setUpSchemaCache();
        $this->useSerializingArrayStore();
    }

    protected function tearDown(): void
    {
        $this->tearDownSchemaCache();

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

    public function testInvalidSchemaCacheContents(): void
    {
        $config = $this->app->make(ConfigRepository::class);

        $filesystem = $this->app->make(Filesystem::class);
        $path = $config->get('lighthouse.schema_cache.path');
        $filesystem->put($path, '');

        $this->expectExceptionObject(new InvalidSchemaCacheContentsException($path, 1));
        $this->graphQL(/** @lang GraphQL */ '
        {
            foo
        }
        ');
    }

    protected function cacheSchema(): void
    {
        /** @var ASTBuilder $astBuilder */
        $astBuilder = $this->app->make(ASTBuilder::class);
        $astBuilder->documentAST();

        $this->app->forgetInstance(ASTBuilder::class);
    }
}
