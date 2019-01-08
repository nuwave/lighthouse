<?php

namespace Tests\Unit\Schema\AST;

use Tests\TestCase;
use League\Flysystem\Filesystem;
use League\Flysystem\Adapter\Local;
use Nuwave\Lighthouse\Schema\Source\SchemaStitcher;
use Illuminate\Contracts\Filesystem\FileNotFoundException;

class SchemaStitcherTest extends TestCase
{
    /**
     * @var string
     */
    const SCHEMA_PATH = __DIR__.'/schema/';

    /**
     * @var string
     */
    const ROOT_SCHEMA_FILENAME = 'root-schema';

    /**
     * @var \League\Flysystem\Filesystem
     */
    protected $filesystem;

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

    protected function assertSchemaResultIsSame(string $expected): void
    {
        $schema = (new SchemaStitcher(self::SCHEMA_PATH.self::ROOT_SCHEMA_FILENAME))->getSchemaString();
        $this->assertSame($expected, $schema);
    }

    protected function putRootSchema(string $schema): void
    {
        $this->filesystem->put(self::ROOT_SCHEMA_FILENAME, $schema);
    }

    /**
     * @test
     */
    public function itThrowsIfRootSchemaIsNotFound(): void
    {
        $this->expectException(FileNotFoundException::class);
        $this->expectExceptionMessageRegExp('/'.self::ROOT_SCHEMA_FILENAME.'/');

        $this->assertSchemaResultIsSame('');
    }

    /**
     * @test
     */
    public function itThrowsIfSchemaImportIsNotFound(): void
    {
        $this->expectException(FileNotFoundException::class);
        $this->expectExceptionMessageRegExp('/does-not-exist.graphql/');

        $foo = <<<'EOT'
#import does-not-exist.graphql

EOT;
        $this->putRootSchema($foo);

        $this->assertSchemaResultIsSame($foo);
    }

    /**
     * @test
     */
    public function itLeavesImportlessFileAsBefore(): void
    {
        $foo = <<<'EOT'
foo

EOT;
        $this->putRootSchema($foo);

        $this->assertSchemaResultIsSame($foo);
    }

    /**
     * @test
     */
    public function itReplacesImportWithFileContent(): void
    {
        $this->putRootSchema(<<<'EOT'
foo
#import bar

EOT
        );

        $this->filesystem->put('bar', <<<'EOT'
bar

EOT
        );

        $this->assertSchemaResultIsSame(<<<'EOT'
foo
bar

EOT
        );
    }

    /**
     * @test
     */
    public function itImportsRecursively(): void
    {
        $this->putRootSchema(<<<'EOT'
foo
#import bar

EOT
        );

        $this->filesystem->put('bar', <<<'EOT'
bar
#import baz
EOT
        );

        $this->filesystem->put('baz', <<<'EOT'
baz

EOT
        );

        $this->assertSchemaResultIsSame(<<<'EOT'
foo
bar
baz

EOT
        );
    }

    /**
     * @test
     */
    public function itImportsFromSubdirectory(): void
    {
        $this->putRootSchema(<<<'EOT'
foo
#import subdir/bar

EOT
        );

        $this->filesystem->createDir('subdir');
        $this->filesystem->put('subdir/bar', <<<'EOT'
bar

EOT
        );

        $this->assertSchemaResultIsSame(<<<'EOT'
foo
bar

EOT
        );
    }

    /**
     * @test
     */
    public function itKeepsIndententation(): void
    {
        $this->putRootSchema(<<<'EOT'
    foo
#import bar

EOT
        );

        $this->filesystem->put('bar', <<<'EOT'
        bar

EOT
        );

        $this->assertSchemaResultIsSame(<<<'EOT'
    foo
        bar

EOT
        );
    }

    /**
     * @test
     */
    public function itImportsViaGlob(): void
    {
        $this->putRootSchema(<<<'EOT'
foo
#import subdir/*.graphql

EOT
        );

        $this->filesystem->createDir('subdir');
        $this->filesystem->put('subdir/bar.graphql', <<<'EOT'
bar

EOT
        );
        $this->filesystem->put('subdir/other.graphql', <<<'EOT'
other

EOT
        );

        $this->assertSchemaResultIsSame(<<<'EOT'
foo
bar
other

EOT
        );
    }

    /**
     * @test
     */
    public function itAddsNewlineToTheEndOfImportedFile(): void
    {
        $this->putRootSchema(<<<'EOT'
foo
#import bar
#import foobar
EOT
        );

        $this->filesystem->put('bar', <<<'EOT'
bar
EOT
        );

        $this->filesystem->put('foobar', <<<'EOT'
foobar
EOT
        );

        $this->assertSchemaResultIsSame(<<<'EOT'
foo
bar
foobar

EOT
        );
    }
}
