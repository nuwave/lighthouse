<?php

namespace Tests\Unit\Console;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Config;
use Nuwave\Lighthouse\Console\CacheCommand;
use Tests\TestCase;

class CacheCommandTest extends TestCase
{
    public function testCachesAST(): void
    {
        $key = Config::get('lighthouse.cache.key');
        $this->assertFalse($this->app->cache->has($key));
        $this->artisan(CacheCommand::class);
        $this->assertTrue(Cache::has($key));
    }
}
