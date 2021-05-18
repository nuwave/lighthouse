<?php

namespace Tests\Console;

use Illuminate\Contracts\Config\Repository as ConfigRepository;
use Nuwave\Lighthouse\Console\ClearCacheCommand;
use function Safe\file_put_contents;
use Tests\TestCase;

class ClearCacheCommandTest extends TestCase
{
    /**
     * @var string
     */
    private $cachePath;

    public function setUp(): void
    {
        parent::setUp();

        $config = app(ConfigRepository::class);
        $this->cachePath = __DIR__.'/../storage/'.__METHOD__.'.php';
        $config->set('lighthouse.cache.path', $this->cachePath);

        file_put_contents($this->cachePath, '<?php return [\'directives\' => []];');
    }

    public function testCachesGraphQLAST(): void
    {
        $this->assertTrue(file_exists($this->cachePath));
        $this->commandTester(new ClearCacheCommand())->execute([]);
        $this->assertNotTrue(file_exists($this->cachePath));
    }
}
