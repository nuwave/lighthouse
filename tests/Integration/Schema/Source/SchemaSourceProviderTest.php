<?php

namespace Tests\Integration\Schema\Source;

use Tests\TestCase;
use League\Flysystem\Filesystem;
use League\Flysystem\Adapter\Local;
use Nuwave\Lighthouse\Schema\Source\SchemaStitcher;
use Nuwave\Lighthouse\Schema\Source\SchemaSourceProvider;

class SchemaSourceProviderTest extends TestCase
{
    const SCHEMA_PATH = __DIR__.'/schema/';

    /**
     * Set up test case.
     */
    protected function setUp()
    {
        parent::setUp();

        $currentDir = new Filesystem(new Local(__DIR__));

        $currentDir->deleteDir('schema');
        $currentDir->createDir('schema');

        $this->filesystem = new Filesystem(new Local(self::SCHEMA_PATH));
    }

    protected function tearDown()
    {
        parent::tearDown();

        $currentDir = new Filesystem(new Local(__DIR__));

        $currentDir->deleteDir('schema');
    }

    /**
     * Define environment setup.
     *
     * @param \Illuminate\Foundation\Application $app
     */
    protected function getEnvironmentSetUp($app)
    {
        $app->singleton(SchemaSourceProvider::class, function () {
            return new SchemaStitcher(config('lighthouse.schema.register', ''));
        });
    }

    /**
     * @test
     */
    public function itCanSetRootPath()
    {
        $this->filesystem->put('foo', 'bar');

        app(SchemaSourceProvider::class)->setRootPath(__DIR__.'/schema/foo');

        $this->assertSame('bar'.PHP_EOL, app(SchemaSourceProvider::class)->getSchemaString());
    }
}
