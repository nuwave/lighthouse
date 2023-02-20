<?php

namespace Tests\Console;

use Illuminate\Contracts\Cache\Repository as CacheRepository;
use Illuminate\Contracts\Config\Repository as ConfigRepository;
use Illuminate\Filesystem\Filesystem;
use Nuwave\Lighthouse\Console\CacheCommand;
use Nuwave\Lighthouse\Exceptions\UnknownCacheVersionException;
use Nuwave\Lighthouse\Schema\AST\DocumentAST;
use Tests\TestCase;
use Tests\TestsSchemaCache;
use Tests\TestsSerialization;

final class CacheCommandTest extends TestCase
{
    use TestsSerialization;
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
        $this->useSerializingArrayStore();
    }

    protected function tearDown(): void
    {
        $this->tearDownSchemaCache();

        parent::tearDown();
    }

    public function testCacheVersion1(): void
    {
        $this->config->set('lighthouse.cache.version', 1);
        $this->config->set('lighthouse.cache.ttl', 60);
        $this->config->set('lighthouse.cache.store', 'array');

        $key = $this->config->get('lighthouse.cache.key');

        $cache = $this->app->make(CacheRepository::class);
        assert($cache instanceof CacheRepository);
        $this->assertFalse($cache->has($key));

        $this->commandTester(new CacheCommand())->execute([]);

        $this->assertTrue($cache->has($key));
        $this->assertInstanceOf(DocumentAST::class, $cache->get($key));
    }

    public function testCacheVersion2(): void
    {
        $this->config->set('lighthouse.cache.version', 2);

        $filesystem = $this->app->make(Filesystem::class);
        assert($filesystem instanceof Filesystem);

        $path = $this->schemaCachePath();
        $this->assertFalse($filesystem->exists($path));

        $this->commandTester(new CacheCommand())->execute([]);

        $this->assertTrue($filesystem->exists($path));
        $this->assertInstanceOf(DocumentAST::class, DocumentAST::fromArray(require $path));
    }

    public function testCacheVersionUnknown(): void
    {
        $this->config->set('lighthouse.cache.version', 3);

        $this->expectException(UnknownCacheVersionException::class);
        $this->commandTester(new CacheCommand())->execute([]);
    }
}
