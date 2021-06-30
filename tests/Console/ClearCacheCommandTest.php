<?php

namespace Tests\Console;

use Illuminate\Cache\Repository as CacheRepository;
use Illuminate\Contracts\Config\Repository as ConfigRepository;
use Illuminate\Filesystem\Filesystem;
use Nuwave\Lighthouse\Console\ClearCacheCommand;
use Nuwave\Lighthouse\Exceptions\UnknownCacheVersionException;
use Tests\TestCase;
use Tests\TestsSchemaCache;

class ClearCacheCommandTest extends TestCase
{
    use TestsSchemaCache;

    /**
     * @var \Illuminate\Contracts\Config\Repository
     */
    protected $config;

    public function setUp(): void
    {
        parent::setUp();

        $this->config = $this->app->make(ConfigRepository::class);
        $this->setUpSchemaCache();
    }

    protected function tearDown(): void
    {
        $this->tearDownSchemaCache();

        parent::tearDown();
    }

    public function testClearsCacheVersion1(): void
    {
        $this->config->set('lighthouse.cache.version', 1);
        $this->config->set('lighthouse.cache.ttl', 60);

        $key = $this->config->get('lighthouse.cache.key');

        /** @var \Illuminate\Cache\Repository $cache */
        $cache = $this->app->make(CacheRepository::class);
        $cache->put($key, 'foo', 60);
        $this->assertTrue($cache->has($key));

        $this->commandTester(new ClearCacheCommand())->execute([]);
        $this->assertFalse($cache->has($key));
    }

    public function testClearsCacheVersion2(): void
    {
        $this->config->set('lighthouse.cache.version', 2);

        /** @var \Illuminate\Filesystem\Filesystem $filesystem */
        $filesystem = $this->app->make(Filesystem::class);

        $path = $this->schemaCachePath();
        $filesystem->put($path, 'foo');
        $this->assertTrue($filesystem->exists($path));

        $this->commandTester(new ClearCacheCommand())->execute([]);
        $this->assertFalse($filesystem->exists($path));
    }

    public function testCacheVersionUnknown(): void
    {
        $this->config->set('lighthouse.cache.version', 3);

        $this->expectException(UnknownCacheVersionException::class);
        $this->commandTester(new ClearCacheCommand())->execute([]);
    }
}
