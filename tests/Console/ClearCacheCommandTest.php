<?php

namespace Tests\Console;

use Illuminate\Cache\Repository as CacheRepository;
use Illuminate\Contracts\Config\Repository as ConfigRepository;
use InvalidArgumentException;
use Nuwave\Lighthouse\Console\ClearCacheCommand;
use function Safe\file_put_contents;
use Tests\TestCase;

class ClearCacheCommandTest extends TestCase
{
    /**
     * @var string
     */
    protected $cachePath;

    public function setUp(): void
    {
        parent::setUp();

        $config = $this->app->make(ConfigRepository::class);
        $this->cachePath = __DIR__ . '/../storage/' . __METHOD__ . '.php';
        $config->set('lighthouse.cache.path', $this->cachePath);

        file_put_contents($this->cachePath, '<?php return [\'directives\' => []];');
    }

    protected function tearDown(): void
    {
        if (file_exists($this->cachePath)) {
            unlink($this->cachePath);
        }

        parent::tearDown();
    }

    public function testClearsCacheGraphQLASTV1(): void
    {
        $config = $this->app->make(ConfigRepository::class);
        $config->set('lighthouse.cache.version', 1);
        $config->set('lighthouse.cache.ttl', 60);

        $key = $config->get('lighthouse.cache.key');

        /** @var CacheRepository $cache */
        $cache = $this->app->make(CacheRepository::class);
        $cache->put($key, serialize(['directives' => []]));
        $this->assertTrue(
            $cache->has($key)
        );

        $this->commandTester(new ClearCacheCommand())->execute([]);

        $this->assertTrue(
            $cache->missing($key)
        );
    }

    public function testClearsCacheGraphQLASTV2(): void
    {
        $config = $this->app->make(ConfigRepository::class);
        $config->set('lighthouse.cache.version', 2);

        $this->assertTrue(file_exists($this->cachePath));
        $this->commandTester(new ClearCacheCommand())->execute([]);
        $this->assertNotTrue(file_exists($this->cachePath));
    }

    public function testInvalidVersion(): void
    {
        $config = $this->app->make(ConfigRepository::class);
        $config->set('lighthouse.cache.version', 3);

        $this->expectException(InvalidArgumentException::class);
        $this->commandTester(new ClearCacheCommand())->execute([]);
    }
}
