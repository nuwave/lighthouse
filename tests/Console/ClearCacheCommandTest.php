<?php declare(strict_types=1);

namespace Tests\Console;

use Illuminate\Cache\Repository as CacheRepository;
use Illuminate\Contracts\Config\Repository as ConfigRepository;
use Illuminate\Filesystem\Filesystem;
use Nuwave\Lighthouse\Console\ClearCacheCommand;
use Nuwave\Lighthouse\Exceptions\UnknownCacheVersionException;
use Tests\TestCase;
use Tests\TestsSchemaCache;

final class ClearCacheCommandTest extends TestCase
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
        $this->config->set('lighthouse.schema_cache.version', 1);
        $this->config->set('lighthouse.schema_cache.ttl', 60);

        $key = $this->config->get('lighthouse.schema_cache.key');

        $cache = $this->app->make(CacheRepository::class);

        $cache->put($key, 'foo', 60);
        $this->assertTrue($cache->has($key));

        $this->commandTester(new ClearCacheCommand())->execute([]);
        $this->assertFalse($cache->has($key));
    }

    public function testClearsCacheVersion2(): void
    {
        $this->config->set('lighthouse.schema_cache.version', 2);

        $filesystem = $this->app->make(Filesystem::class);
        $path = $this->schemaCachePath();
        $filesystem->put($path, 'foo');
        $this->assertTrue($filesystem->exists($path));

        $this->commandTester(new ClearCacheCommand())->execute([]);
        $this->assertFalse($filesystem->exists($path));
    }

    public function testCacheVersionUnknown(): void
    {
        $this->config->set('lighthouse.schema_cache.version', 3);

        $this->expectException(UnknownCacheVersionException::class);
        $this->commandTester(new ClearCacheCommand())->execute([]);
    }
}
