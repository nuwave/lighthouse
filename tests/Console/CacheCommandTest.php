<?php declare(strict_types=1);

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
    use TestsSchemaCache;

    public function setUp(): void
    {
        parent::setUp();

        $this->setUpSchemaCache();
    }

    protected function tearDown(): void
    {
        $this->tearDownSchemaCache();

        parent::tearDown();
    }

    public function testCache(): void
    {
        $filesystem = $this->app->make(Filesystem::class);
        $path = $this->schemaCachePath();
        $this->assertFalse($filesystem->exists($path));

        $this->commandTester(new CacheCommand())->execute([]);

        $this->assertTrue($filesystem->exists($path));
        DocumentAST::fromArray(require $path);
    }
}
