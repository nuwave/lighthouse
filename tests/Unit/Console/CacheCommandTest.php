<?php

namespace Tests\Unit\Console;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Config;
use Nuwave\Lighthouse\Console\CacheCommand;
use Tests\TestCase;

class CacheCommandTest extends TestCase
{
    public function testItCachesGraphQLAST(): void
    {
        $key = Config::get('lighthouse.cache.key');
        $tester = $this->commandTester(new CacheCommand());
        $tester->execute([]);
        $this->assertTrue(Cache::has($key));
    }
}
