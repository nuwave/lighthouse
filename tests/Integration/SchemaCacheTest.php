<?php declare(strict_types=1);

namespace Tests\Integration;

use Illuminate\Contracts\Config\Repository as ConfigRepository;
use Illuminate\Filesystem\Filesystem;
use Nuwave\Lighthouse\Schema\AST\ASTBuilder;
use Nuwave\Lighthouse\Schema\AST\DocumentAST;
use Nuwave\Lighthouse\Support\Utils;
use Tests\TestCase;
use Tests\TestsSchemaCache;
use Tests\TestsSerialization;
use Tests\Utils\Models\Comment;

use function Safe\ini_get;
use function Safe\ini_set;

final class SchemaCacheTest extends TestCase
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
        $this->schema = /** @lang GraphQL */ <<<'GRAPHQL'
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
        GRAPHQL;
        $this->cacheSchema();

        $comment = new Comment();
        $comment->comment = 'foo';
        $this->mockResolver($comment);

        $this->graphQL(/** @lang GraphQL */ <<<'GRAPHQL'
        {
            foo {
                ... on Comment {
                    comment
                }
            }
        }
        GRAPHQL)->assertExactJson([
            'data' => [
                'foo' => [
                    'comment' => $comment->comment,
                ],
            ],
        ]);
    }

    public function testRebuildsInvalidFile(): void
    {
        $this->schema = /** @lang GraphQL */ <<<'GRAPHQL'
        type Query {
            foo: Int @mock
        }
        GRAPHQL;

        $config = $this->app->make(ConfigRepository::class);

        $filesystem = $this->app->make(Filesystem::class);
        $path = $config->get('lighthouse.schema_cache.path');
        $filesystem->put($path, '');

        $this->mockResolver(42);

        $this->graphQL(/** @lang GraphQL */ <<<'GRAPHQL'
        {
            foo
        }
        GRAPHQL)->assertExactJson([
            'data' => [
                'foo' => 42,
            ],
        ]);

        $this->assertIsArray(require $path);
    }

    public function testReusesLoadedCacheFileWhileUnchanged(): void
    {
        $this->schema = /** @lang GraphQL */ <<<'GRAPHQL'
        type Query {
            foo: Int
        }
        GRAPHQL;
        $this->cacheSchema();

        $filesystem = new class extends Filesystem {
            public int $existsCalls = 0;

            public function exists($path): bool
            {
                ++$this->existsCalls;

                return parent::exists($path);
            }
        };
        $this->app->instance(Filesystem::class, $filesystem);

        // Simulates multiple requests in a long-running process such as Laravel Octane
        $hash = $this->freshDocumentAST()->hash;
        $this->assertSame($hash, $this->freshDocumentAST()->hash);
        $this->assertSame($hash, $this->freshDocumentAST()->hash);

        $this->assertSame(1, $filesystem->existsCalls, 'Expected the cache file to be required only once.');
    }

    public function testReloadsCacheFileWhenItChanges(): void
    {
        $this->schema = /** @lang GraphQL */ <<<'GRAPHQL'
        type Query {
            foo: Int
        }
        GRAPHQL;
        $this->cacheSchema();
        $this->assertArrayNotHasKey('Bar', $this->freshDocumentAST()->types);

        // Simulates running `php artisan lighthouse:cache` in another process
        $changed = DocumentAST::fromSource(/** @lang GraphQL */ <<<'GRAPHQL'
        type Query {
            foo: Int
        }

        type Bar {
            baz: Int
        }
        GRAPHQL);
        $variable = var_export($changed->toArray(), true);
        Utils::atomicPut(
            filesystem: $this->app->make(Filesystem::class),
            path: $this->schemaCachePath(),
            contents: "<?php return {$variable};",
        );

        $this->assertArrayHasKey('Bar', $this->freshDocumentAST()->types);
    }

    public function testReloadsCacheFileWhenItChangesWithOPcache(): void
    {
        if (! extension_loaded('Zend OPcache') || ! filter_var(ini_get('opcache.enable_cli'), FILTER_VALIDATE_BOOL)) {
            $this->markTestSkipped('Requires OPcache, e.g. run with -d opcache.enable_cli=1.');
        }

        // Make OPcache serve the compiled file without checking for changes on disk
        $revalidateFreq = ini_set('opcache.revalidate_freq', '3600');
        $fileUpdateProtection = ini_set('opcache.file_update_protection', '0');

        try {
            $this->testReloadsCacheFileWhenItChanges();
        } finally {
            ini_set('opcache.revalidate_freq', $revalidateFreq);
            ini_set('opcache.file_update_protection', $fileUpdateProtection);
        }
    }

    public function testRebuildsWhenCacheFileIsDeleted(): void
    {
        $this->schema = /** @lang GraphQL */ <<<'GRAPHQL'
        type Query {
            foo: Int
        }
        GRAPHQL;
        $this->cacheSchema();
        $this->freshDocumentAST();

        $filesystem = $this->app->make(Filesystem::class);
        $filesystem->delete($this->schemaCachePath());

        $this->schema = /** @lang GraphQL */ <<<'GRAPHQL'
        type Query {
            foo: Int
        }

        type Bar {
            baz: Int
        }
        GRAPHQL;

        $this->assertArrayHasKey('Bar', $this->freshDocumentAST()->types);
        $this->assertFileExists($this->schemaCachePath());
    }

    private function freshDocumentAST(): DocumentAST
    {
        $this->app->forgetInstance(ASTBuilder::class);

        return $this->app->make(ASTBuilder::class)->documentAST();
    }

    private function cacheSchema(): void
    {
        $astBuilder = $this->app->make(ASTBuilder::class);
        $astBuilder->documentAST();

        $this->app->forgetInstance(ASTBuilder::class);
    }
}
