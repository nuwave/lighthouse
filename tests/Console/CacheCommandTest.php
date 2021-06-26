<?php

namespace Tests\Console;

use Illuminate\Console\Command;
use Illuminate\Contracts\Cache\Repository as CacheRepository;
use Illuminate\Contracts\Config\Repository as ConfigRepository;
use InvalidArgumentException;
use Nuwave\Lighthouse\Console\CacheCommand;
use function Safe\unlink;
use Tests\TestCase;

class CacheCommandTest extends TestCase
{
    /**
     * @var string
     */
    protected $cachePath;

    public function setUp(): void
    {
        parent::setUp();

        $config = $this->app->make(ConfigRepository::class);
        $config->set('lighthouse.cache.enable', true);
        $this->cachePath = __DIR__.'/../storage/'.__METHOD__.'.php';
        $config->set('lighthouse.cache.path', $this->cachePath);
    }

    protected function tearDown(): void
    {
        if (file_exists($this->cachePath)) {
            unlink($this->cachePath);
        }

        parent::tearDown();
    }

    public function testCachesGraphQLASTV1(): void
    {
        $config = $this->app->make(ConfigRepository::class);
        $config->set('lighthouse.cache.version', 1);
        $config->set('lighthouse.cache.ttl', 60);

        $key = $config->get('lighthouse.cache.key');

        $cache = $this->app->make(CacheRepository::class);
        $this->assertFalse(
            $cache->has($key)
        );

        $this->commandTester(new CacheCommand)->execute([]);

        $this->assertTrue(
            $cache->has($key)
        );
    }

    public function testCachesGraphQLASTV2(): void
    {
        $config = $this->app->make(ConfigRepository::class);
        $config->set('lighthouse.cache.version', 2);

        $this->assertNotTrue(file_exists($this->cachePath));
        $this->commandTester(new CacheCommand)->execute([]);
        $this->assertTrue(file_exists($this->cachePath));
    }

    public function testInvalidVersion(): void
    {
        $config = $this->app->make(ConfigRepository::class);
        $config->set('lighthouse.cache.version', 3);

        $this->expectException(InvalidArgumentException::class);
        $this->commandTester(new CacheCommand)->execute([]);
    }
}
