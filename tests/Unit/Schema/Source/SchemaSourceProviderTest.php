<?php

namespace Tests\Unit\Schema\Source;

use League\Flysystem\Adapter\Local;
use League\Flysystem\Filesystem;
use Nuwave\Lighthouse\Schema\Source\SchemaSourceProvider;
use Nuwave\Lighthouse\Schema\Source\SchemaStitcher;
use Tests\TestCase;

class SchemaSourceProviderTest extends TestCase
{
    /**
     * @var string
     */
    const SCHEMA_PATH = __DIR__.'/schema/';

    /**
     * @var \League\Flysystem\Filesystem
     */
    protected $filesystem;

    protected function setUp(): void
    {
        parent::setUp();

        app()->singleton(SchemaSourceProvider::class, function () {
            return new SchemaStitcher(config('lighthouse.schema.register', ''));
        });

        $currentDir = new Filesystem(new Local(__DIR__));

        $currentDir->deleteDir('schema');
        $currentDir->createDir('schema');

        $this->filesystem = new Filesystem(new Local(self::SCHEMA_PATH));
    }

    protected function tearDown(): void
    {
        parent::tearDown();

        $currentDir = new Filesystem(new Local(__DIR__));

        $currentDir->deleteDir('schema');
    }

    public function testCanSetRootPath(): void
    {
        $this->filesystem->put('foo', 'bar');

        app(SchemaSourceProvider::class)->setRootPath(__DIR__.'/schema/foo');

        $this->assertSame('bar'.PHP_EOL, app(SchemaSourceProvider::class)->getSchemaString());
    }
}
