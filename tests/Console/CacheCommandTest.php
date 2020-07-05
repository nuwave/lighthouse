<?php

namespace Tests\Console;

use Illuminate\Contracts\Cache\Repository as CacheRepository;
use Illuminate\Contracts\Config\Repository as ConfigRepository;
use Nuwave\Lighthouse\Console\CacheCommand;
use Tests\TestCase;

class CacheCommandTest extends TestCase
{
    public function testItCachesGraphQLAST(): void
    {
        $config = app(ConfigRepository::class);
        $config->set('lighthouse.cache.ttl', 60);

        $key = $config->get('lighthouse.cache.key');

        $cache = app(CacheRepository::class);
        $this->assertFalse(
            $cache->has($key)
        );

        $this->commandTester(new CacheCommand)->execute([]);

        $this->assertTrue(
            $cache->has($key)
        );
    }
}
