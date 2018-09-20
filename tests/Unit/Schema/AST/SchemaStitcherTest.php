<?php

namespace Tests\Unit\Schema\AST;

use PHPUnit\Framework\TestCase;
use League\Flysystem\Filesystem;
use League\Flysystem\Adapter\Local;
use Nuwave\Lighthouse\Schema\Source\SchemaStitcher;

class SchemaStitcherTest extends TestCase
{
    const SCHEMA_PATH = __DIR__ . '/schema/';
    const ROOT_SCHEMA_FILENAME = 'root-schema';

    /**
     * @var Filesystem
     */
    protected $filesystem;

    /**
     * Set up test case.
     */
    protected function setUp()
    {
        $currentDir = new Filesystem(new Local(__DIR__));

        $currentDir->deleteDir('schema');
        $currentDir->createDir('schema');

        $this->filesystem = new Filesystem(new Local(self::SCHEMA_PATH));
    }

    protected function tearDown()
    {
        $currentDir = new Filesystem(new Local(__DIR__));

        $currentDir->deleteDir('schema');
    }

    protected function assertSchemaResultIsSame(string $expected)
    {
        $schema = (new SchemaStitcher(self::SCHEMA_PATH . self::ROOT_SCHEMA_FILENAME))->getSchemaString();
        $this->assertSame($expected, $schema);
    }

    protected function putRootSchema(string $schema)
    {
        $this->filesystem->put(self::ROOT_SCHEMA_FILENAME, $schema);
    }

    /**
     * @test
     */
    public function itLeavesImportlessFileAsBefore()
    {
        $foo = <<<EOT
foo

EOT;
        $this->putRootSchema($foo);
        $this->assertSchemaResultIsSame($foo);
    }

    /**
     * @test
     */
    public function itReplacesImportWithFileContent()
    {
        $this->putRootSchema(<<<EOT
foo
#import bar

EOT
        );

        $this->filesystem->put('bar', <<<EOT
bar

EOT
        );

        $this->assertSchemaResultIsSame(<<<EOT
foo
bar

EOT
        );
    }

    /**
     * @test
     */
    public function itImportsRecursively()
    {
        $this->putRootSchema(<<<EOT
foo
#import bar

EOT
        );

        $this->filesystem->put('bar', <<<EOT
bar
#import baz
EOT
        );

        $this->filesystem->put('baz', <<<EOT
baz

EOT
        );

        $this->assertSchemaResultIsSame(<<<EOT
foo
bar
baz

EOT
        );
    }

    /**
     * @test
     */
    public function itImportsFromSubdirectory()
    {
        $this->putRootSchema(<<<EOT
foo
#import subdir/bar

EOT
        );

        $this->filesystem->createDir('subdir');
        $this->filesystem->put('subdir/bar', <<<EOT
bar

EOT
        );

        $this->assertSchemaResultIsSame(<<<EOT
foo
bar

EOT
        );
    }

    /**
     * @test
     */
    public function itKeepsIndententation()
    {
        $this->putRootSchema(<<<EOT
    foo
#import bar

EOT
        );

        $this->filesystem->put('bar', <<<EOT
        bar

EOT
        );

        $this->assertSchemaResultIsSame(<<<EOT
    foo
        bar

EOT
        );
    }

    /**
     * @test
     */
    public function itImportsViaGlob()
    {
        $this->putRootSchema(<<<EOT
foo
#import subdir/*.graphql

EOT
        );

        $this->filesystem->createDir('subdir');
        $this->filesystem->put('subdir/bar.graphql', <<<EOT
bar

EOT
        );
        $this->filesystem->put('subdir/other.graphql', <<<EOT
other

EOT
        );

        $this->assertSchemaResultIsSame(<<<EOT
foo
bar
other

EOT
        );
    }
}
