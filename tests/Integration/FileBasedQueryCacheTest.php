<?php

namespace Tests\Integration;

use Illuminate\Config\Repository as ConfigRepository;
use Illuminate\Filesystem\Filesystem;
use Tests\TestCase;

class FileBasedQueryCacheTest extends TestCase
{
    const STORAGE_DIR = __DIR__ . '/../storage';

    protected function tearDown(): void
    {
        $filesystem = $this->app->make(Filesystem::class);
        // remove all files except dotfiles
        $filesystem->delete(
            $filesystem->files(self::STORAGE_DIR)
        );
        parent::tearDown();
    }

    public function testFileCacheGetsCreated()
    {
        $config = $this->app->make(ConfigRepository::class);
        $config->set('lighthouse.query_cache.enable', true);
        $config->set('lighthouse.query_cache.use_file_cache', true);
        $config->set('lighthouse.query_cache.file_cache_path', self::STORAGE_DIR);

        $this->graphQL(/** @lang GraphQL */<<<GQL
        query AskFoo {
            foo
        }
        GQL);

        $this->assertFileExists(self::STORAGE_DIR . '/0f0be96218c966b815fd0a976801668f6c7dae61e184d85825ced04a0bb6f139.php');
    }
}
