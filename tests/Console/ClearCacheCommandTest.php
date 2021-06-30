<?php

namespace Tests\Console;

use Illuminate\Cache\Repository as CacheRepository;
use Illuminate\Contracts\Config\Repository as ConfigRepository;
use Illuminate\Filesystem\Filesystem;
use Nuwave\Lighthouse\Console\ClearCacheCommand;
use Nuwave\Lighthouse\Exceptions\UnknownCacheVersionException;
use Tests\TestCase;

class ClearCacheCommandTest extends TestCase
{
    /**
     * @var \Illuminate\Contracts\Config\Repository
     */
    protected $config;

    /**
     * @var string
     */
    protected $cachePath;

    public function setUp(): void
    {
        parent::setUp();

        $this->config = $this->app->make(ConfigRepository::class);
        $this->cachePath = __DIR__.'/../storage/'.__METHOD__.'.php';
        $this->config->set('lighthouse.cache.path', $this->cachePath);
    }

    protected function tearDown(): void
    {
        /** @var \Illuminate\Filesystem\Filesystem $filesystem */
        $filesystem = $this->app->make(Filesystem::class);
        $filesystem->delete($this->cachePath);

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
        $filesystem->put($this->cachePath, 'foo');
        $this->assertTrue($filesystem->exists($this->cachePath));

        $this->commandTester(new ClearCacheCommand())->execute([]);
        $this->assertFalse($filesystem->exists($this->cachePath));
    }

    public function testCacheVersionUnknown(): void
    {
        $this->config->set('lighthouse.cache.version', 3);

        $this->expectException(UnknownCacheVersionException::class);
        $this->commandTester(new ClearCacheCommand())->execute([]);
    }
}
