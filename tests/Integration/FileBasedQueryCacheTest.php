<?php declare(strict_types=1);

namespace Tests\Integration;

use Illuminate\Config\Repository as ConfigRepository;
use Illuminate\Filesystem\FilesystemAdapter;
use Illuminate\Support\Facades\Storage;
use Nuwave\Lighthouse\Exceptions\InvalidQueryCacheContentsException;
use Tests\TestCase;

final class FileBasedQueryCacheTest extends TestCase
{
    private FilesystemAdapter $filesystem;

    protected function setUp(): void
    {
        parent::setUp();

        $filesystem = Storage::fake();
        assert($filesystem instanceof FilesystemAdapter);
        $this->filesystem = $filesystem;

        $config = $this->app->make(ConfigRepository::class);
        $config->set('lighthouse.query_cache.enable', true);
        $config->set('lighthouse.query_cache.use_file_cache', true);
        $config->set('lighthouse.query_cache.file_cache_path', $this->filesystem->path(''));
    }

    public function testFileCacheGetsCreated(): void
    {
        $this->graphQL(/** @lang GraphQL */ <<<GQL
        query AskFoo {
            foo
        }
        GQL);

        $this->filesystem->assertExists('query-0f0be96218c966b815fd0a976801668f6c7dae61e184d85825ced04a0bb6f139.php');
    }

    public function testCacheFileContainsNonsense(): void
    {
        $this->filesystem->put(
            'query-0f0be96218c966b815fd0a976801668f6c7dae61e184d85825ced04a0bb6f139.php',
            /** @lang PHP */
            '<?php return "foo";',
        );

        $this->expectException(InvalidQueryCacheContentsException::class);
        $this->graphQL(/** @lang GraphQL */ <<<GQL
        query AskFoo {
            foo
        }
        GQL);
    }
}
