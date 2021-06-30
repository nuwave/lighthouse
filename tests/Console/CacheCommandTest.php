<?php

namespace Tests\Console;

use Illuminate\Contracts\Cache\Repository as CacheRepository;
use Illuminate\Contracts\Config\Repository as ConfigRepository;
use Illuminate\Filesystem\Filesystem;
use Nuwave\Lighthouse\Console\CacheCommand;
use Nuwave\Lighthouse\Exceptions\UnknownCacheVersionException;
use Nuwave\Lighthouse\Schema\AST\DocumentAST;
use Tests\TestCase;
use Tests\TestsSerialization;

class CacheCommandTest extends TestCase
{
    use TestsSerialization;

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

        $this->config->set('lighthouse.cache.enable', true);

        $this->cachePath = __DIR__.'/../storage/'.__METHOD__.'.php';
        $this->config->set('lighthouse.cache.path', $this->cachePath);

        $this->useSerializingArrayStore($this->app);
    }

    protected function tearDown(): void
    {
        /** @var \Illuminate\Filesystem\Filesystem $filesystem */
        $filesystem = $this->app->make(Filesystem::class);
        $filesystem->delete($this->cachePath);

        parent::tearDown();
    }

    public function testCacheVersion1(): void
    {
        $this->config->set('lighthouse.cache.version', 1);
        $this->config->set('lighthouse.cache.ttl', 60);
        $this->config->set('lighthouse.cache.store', 'array');

        $key = $this->config->get('lighthouse.cache.key');

        /** @var \Illuminate\Contracts\Cache\Repository $cache */
        $cache = $this->app->make(CacheRepository::class);
        $this->assertFalse($cache->has($key));

        $this->commandTester(new CacheCommand)->execute([]);

        $this->assertTrue($cache->has($key));
        $this->assertInstanceOf(DocumentAST::class, $cache->get($key));
    }

    public function testCacheVersion2(): void
    {
        $this->config->set('lighthouse.cache.version', 2);

        /** @var \Illuminate\Filesystem\Filesystem $filesystem */
        $filesystem = $this->app->make(Filesystem::class);
        $this->assertFalse($filesystem->exists($this->cachePath));

        $this->commandTester(new CacheCommand)->execute([]);

        $this->assertTrue($filesystem->exists($this->cachePath));
        $this->assertInstanceOf(DocumentAST::class, DocumentAST::fromArray(require $this->cachePath));
    }

    public function testCacheVersionUnknown(): void
    {
        $this->config->set('lighthouse.cache.version', 3);

        $this->expectException(UnknownCacheVersionException::class);
        $this->commandTester(new CacheCommand)->execute([]);
    }
}
