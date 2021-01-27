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
    public const SCHEMA_PATH = __DIR__.'/schema/';

    /**
     * @var \League\Flysystem\Filesystem
     */
    protected $filesystem;

    public function setUp(): void
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
}
