<?php

namespace Tests\Unit\Console;

use Nuwave\Lighthouse\Console\CacheCommand;
use Tests\TestCase;

class CacheCommandTest extends TestCase
{
    public function testItCachesGraphQLAST(): void
    {
        $key = config('lighthouse.cache.key');
        config(['lighthouse.cache.ttl' => 60]);
        $this->commandTester(new CacheCommand)->execute([]);
        $this->assertTrue(cache()->has($key));
    }
}
