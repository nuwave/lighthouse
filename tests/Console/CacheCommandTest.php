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

    protected ConfigRepository $config;

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
        $this->config->set('lighthouse.schema_cache.version', 1);
        $this->config->set('lighthouse.schema_cache.ttl', 60);
        $this->config->set('lighthouse.schema_cache.store', 'array');

        $key = $this->config->get('lighthouse.schema_cache.key');

        $cache = $this->app->make(CacheRepository::class);
        $this->assertFalse($cache->has($key));

        $this->commandTester(new CacheCommand())->execute([]);

        $this->assertTrue($cache->has($key));
        $this->assertInstanceOf(DocumentAST::class, $cache->get($key));
    }

    public function testCacheVersion2(): void
    {
        $this->config->set('lighthouse.schema_cache.version', 2);

        $filesystem = $this->app->make(Filesystem::class);
        $path = $this->schemaCachePath();
        $this->assertFalse($filesystem->exists($path));

        $this->commandTester(new CacheCommand())->execute([]);

        $this->assertTrue($filesystem->exists($path));
        DocumentAST::fromArray(require $path);
    }

    public function testCacheVersionUnknown(): void
    {
        $this->config->set('lighthouse.schema_cache.version', 3);

        $commandTester = $this->commandTester(new CacheCommand());

        $this->expectException(UnknownCacheVersionException::class);
        $commandTester->execute([]);
    }
}
