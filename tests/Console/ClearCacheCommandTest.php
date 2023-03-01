<?php declare(strict_types=1);

namespace Tests\Console;

use Illuminate\Filesystem\Filesystem;
use Nuwave\Lighthouse\Console\ClearCacheCommand;
use Tests\TestCase;
use Tests\TestsSchemaCache;

final class ClearCacheCommandTest extends TestCase
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

    public function testClearsCache(): void
    {
        $filesystem = $this->app->make(Filesystem::class);
        $path = $this->schemaCachePath();
        $filesystem->put($path, 'foo');
        $this->assertTrue($filesystem->exists($path));

        $this->commandTester(new ClearCacheCommand())->execute([]);
        $this->assertFalse($filesystem->exists($path));
    }
}
