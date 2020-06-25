<?php

namespace Tests\Unit\Console;

use Nuwave\Lighthouse\Console\CacheCommand;
use Tests\TestCase;

class CacheCommandTest extends TestCase
{
    public function testItCachesGraphQLAST(): void
    {
        $key = config('lighthouse.cache.key');
        $tester = $this->commandTester(new CacheCommand());
        $tester->execute([]);
        $this->assertTrue(cache()->has($key));
    }
}
