<?php

namespace Tests\Unit\Schema\AST;

use Illuminate\Contracts\Filesystem\FileNotFoundException;
use League\Flysystem\Adapter\Local;
use League\Flysystem\Filesystem;
use Nuwave\Lighthouse\Schema\Source\SchemaStitcher;
use Tests\TestCase;

class SchemaStitcherTest extends TestCase
{
    /**
     * @var string
     */
    public const SCHEMA_PATH = __DIR__.'/schema/';

    /**
     * @var string
     */
    public const ROOT_SCHEMA_FILENAME = 'root-schema';

    /**
     * @var \League\Flysystem\Filesystem
     */
    protected $filesystem;

    public function setUp(): void
    {
        parent::setUp();

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

    protected function assertSchemaResultIsSame(string $expected): void
    {
        $schema = (new SchemaStitcher(self::SCHEMA_PATH.self::ROOT_SCHEMA_FILENAME))->getSchemaString();
        $this->assertSame($expected, $schema);
    }

    protected function putRootSchema(string $schema): void
    {
        $this->filesystem->put(self::ROOT_SCHEMA_FILENAME, $schema);
    }

    public function testThrowsIfRootSchemaIsNotFound(): void
    {
        $this->expectException(FileNotFoundException::class);

        $this->assertSchemaResultIsSame('');
    }

    public function testThrowsIfSchemaImportIsNotFound(): void
    {
        $this->expectException(FileNotFoundException::class);

        $foo = <<<'EOT'
#import does-not-exist.graphql

EOT;
        $this->putRootSchema($foo);

        $this->assertSchemaResultIsSame($foo);
    }

    public function testLeavesImportlessFileAsBefore(): void
    {
        $foo = <<<'EOT'
foo

EOT;
        $this->putRootSchema($foo);

        $this->assertSchemaResultIsSame($foo);
    }

    public function testReplacesImportWithFileContent(): void
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

    public function testImportsRecursively(): void
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

    public function testImportsFromSubdirectory(): void
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

    public function testKeepsIndententation(): void
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

    public function testImportsViaGlob(): void
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

    public function testAddsNewlineToTheEndOfImportedFile(): void
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
