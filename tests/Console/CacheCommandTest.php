<?php

namespace Tests\Console;

use Illuminate\Contracts\Config\Repository as ConfigRepository;
use Nuwave\Lighthouse\Console\CacheCommand;
use function Safe\unlink;
use Tests\TestCase;

class CacheCommandTest extends TestCase
{
    /**
     * @var string
     */
    private $cachePath;

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
        unlink($this->cachePath);
        parent::tearDown();
    }

    public function testCachesGraphQLAST(): void
    {
        $this->assertNotTrue(file_exists($this->cachePath));
        $this->commandTester(new CacheCommand)->execute([]);
        $this->assertTrue(file_exists($this->cachePath));
    }
}
