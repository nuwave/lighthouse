<?php

namespace Tests\Unit\Console;

use Nuwave\Lighthouse\Console\CacheCommand;
use Tests\TestCase;

class CacheCommandTest extends TestCase
{
    public function testCachesAST(): void
    {
        $key = $this->app->config->get('lighthouse.cache.key');
        $this->assertFalse($this->app->cache->has($key));
        $this->artisan(CacheCommand::class);
        $this->assertTrue($this->app->cache->has($key));
    }
}
