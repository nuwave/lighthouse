<?php declare(strict_types=1);

namespace Tests\Console;

use Illuminate\Filesystem\Filesystem;
use Nuwave\Lighthouse\Console\CacheCommand;
use Nuwave\Lighthouse\Schema\AST\DocumentAST;
use Tests\TestCase;
use Tests\TestsSchemaCache;

final class CacheCommandTest extends TestCase
{
    use TestsSchemaCache;

    protected function setUp(): void
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
