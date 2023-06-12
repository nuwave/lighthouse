<?php declare(strict_types=1);

namespace Tests\Unit\Schema\Source;

use Illuminate\Contracts\Filesystem\FileNotFoundException;
use Nuwave\Lighthouse\Schema\Source\SchemaStitcher;
use Tests\TestCase;

final class SchemaStitcherTest extends TestCase
{
    public const SCHEMA_PATH = __DIR__ . '/schema/';

    public const ROOT_SCHEMA_FILENAME = 'root-schema';

    public const ROOT_SCHEMA_PATH = self::SCHEMA_PATH . self::ROOT_SCHEMA_FILENAME;

    protected function setUp(): void
    {
        parent::setUp();

        // uses the short `-p` because `--parent` is not available on macOS
        // @phpstan-ignore-next-line using the Safe variant crashes PHPStan
        exec('mkdir --parents ' . self::SCHEMA_PATH);
    }

    protected function tearDown(): void
    {
        // @phpstan-ignore-next-line using the Safe variant crashes PHPStan
        exec('rm -rf ' . self::SCHEMA_PATH);

        parent::tearDown();
    }

    protected function assertSchemaResultIsSame(string $expected): void
    {
        $schema = (new SchemaStitcher(self::ROOT_SCHEMA_PATH))->getSchemaString();
        $this->assertSame($expected, $schema);
    }

    protected function putRootSchema(string $schema): void
    {
        \Safe\file_put_contents(self::ROOT_SCHEMA_PATH, $schema);
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
        $this->putRootSchema(
            <<<'EOT'
foo
#import bar

EOT
        );

        \Safe\file_put_contents(
            self::SCHEMA_PATH . 'bar',
            <<<'EOT'
bar

EOT
        );

        $this->assertSchemaResultIsSame(
            <<<'EOT'
foo
bar

EOT
        );
    }

    public function testImportsRecursively(): void
    {
        $this->putRootSchema(
            <<<'EOT'
foo
#import bar

EOT
        );

        \Safe\file_put_contents(
            self::SCHEMA_PATH . 'bar',
            <<<'EOT'
bar
#import baz
EOT
        );

        \Safe\file_put_contents(
            self::SCHEMA_PATH . 'baz',
            <<<'EOT'
baz

EOT
        );

        $this->assertSchemaResultIsSame(
            <<<'EOT'
foo
bar
baz

EOT
        );
    }

    public function testImportsFromSubdirectory(): void
    {
        $this->putRootSchema(
            <<<'EOT'
foo
#import subdir/bar

EOT
        );

        \Safe\mkdir(self::SCHEMA_PATH . 'subdir');
        \Safe\file_put_contents(
            self::SCHEMA_PATH . 'subdir/bar',
            <<<'EOT'
bar

EOT
        );

        $this->assertSchemaResultIsSame(
            <<<'EOT'
foo
bar

EOT
        );
    }

    public function testKeepsIndententation(): void
    {
        $this->putRootSchema(
            <<<'EOT'
    foo
#import bar

EOT
        );

        \Safe\file_put_contents(
            self::SCHEMA_PATH . 'bar',
            <<<'EOT'
        bar

EOT
        );

        $this->assertSchemaResultIsSame(
            <<<'EOT'
    foo
        bar

EOT
        );
    }

    public function testImportsViaGlob(): void
    {
        $this->putRootSchema(
            <<<'EOT'
foo
#import subdir/*.graphql

EOT
        );

        \Safe\mkdir(self::SCHEMA_PATH . 'subdir');
        \Safe\file_put_contents(
            self::SCHEMA_PATH . 'subdir/bar.graphql',
            <<<'EOT'
bar

EOT
        );
        \Safe\file_put_contents(
            self::SCHEMA_PATH . 'subdir/other.graphql',
            <<<'EOT'
other

EOT
        );

        $this->assertSchemaResultIsSame(
            <<<'EOT'
foo
bar
other

EOT
        );
    }

    public function testAddsNewlineToTheEndOfImportedFile(): void
    {
        $this->putRootSchema(
            <<<'EOT'
foo
#import bar
#import foobar
EOT
        );

        \Safe\file_put_contents(
            self::SCHEMA_PATH . 'bar',
            <<<'EOT'
bar
EOT
        );

        \Safe\file_put_contents(
            self::SCHEMA_PATH . 'foobar',
            <<<'EOT'
foobar
EOT
        );

        $this->assertSchemaResultIsSame(
            <<<'EOT'
foo
bar
foobar

EOT
        );
    }
}
