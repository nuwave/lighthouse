<?php declare(strict_types=1);

namespace Tests\Unit\Support;

use Illuminate\Filesystem\Filesystem;
use Illuminate\Support\Facades\Storage;
use Nuwave\Lighthouse\Support\Utils;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

final class UtilsTest extends TestCase
{
    /** @dataProvider nameToEnumValueName */
    #[DataProvider('nameToEnumValueName')]
    public function testToEnumValueName(string $name, string $enumValueName): void
    {
        $this->assertSame(
            $enumValueName,
            Utils::toEnumValueName($name),
        );
    }

    /** @return iterable<array{string, string}> */
    public static function nameToEnumValueName(): iterable
    {
        yield ['column_name', 'COLUMN_NAME'];
        yield ['columnName', 'COLUMN_NAME'];
        yield ['some.nested.column_name', 'SOME_NESTED_COLUMN_NAME'];
        yield ['$columnName', 'COLUMN_NAME'];
        yield ['123_column_name', '_123_COLUMN_NAME'];
        yield ['123Column$Name', '_123_COLUMN_NAME'];
    }

    public function testAtomicPutDeletesTempFileOnFailedWrite(): void
    {
        // Simulates a full disk
        $filesystem = new class extends Filesystem {
            public function put($path, $contents, $lock = false): int|bool
            {
                parent::put($path, substr($contents, 0, 3));

                throw new \ErrorException('file_put_contents(): Write failed');
            }
        };

        $disk = Storage::fake();

        try {
            Utils::atomicPut(filesystem: $filesystem, path: $disk->path('file.php'), contents: '<?php return [];');
            $this->fail('Expected the write to fail.');
        } catch (\ErrorException) {
            $this->assertSame([], $disk->allFiles());
        }
    }

    public function testAtomicPutDeletesTempFileOnFailedMove(): void
    {
        $filesystem = new class extends Filesystem {
            public function move($path, $target): bool
            {
                throw new \ErrorException('rename(): Permission denied');
            }
        };

        $disk = Storage::fake();

        try {
            Utils::atomicPut(filesystem: $filesystem, path: $disk->path('file.php'), contents: '<?php return [];');
            $this->fail('Expected the move to fail.');
        } catch (\ErrorException) {
            $this->assertSame([], $disk->allFiles());
        }
    }

    public function testAtomicPutThrowsAndDeletesTempFileWhenWriteReturnsFalse(): void
    {
        $filesystem = new class extends Filesystem {
            public function put($path, $contents, $lock = false): int|bool
            {
                parent::put($path, substr($contents, 0, 3));

                return false;
            }
        };

        $disk = Storage::fake();

        try {
            Utils::atomicPut(filesystem: $filesystem, path: $disk->path('file.php'), contents: '<?php return [];');
            $this->fail('Expected the write to fail.');
        } catch (\RuntimeException) {
            $this->assertSame([], $disk->allFiles());
        }
    }

    public function testAtomicPutThrowsAndDeletesTempFileWhenMoveReturnsFalse(): void
    {
        $filesystem = new class extends Filesystem {
            public function move($path, $target): bool
            {
                return false;
            }
        };

        $disk = Storage::fake();

        try {
            Utils::atomicPut(filesystem: $filesystem, path: $disk->path('file.php'), contents: '<?php return [];');
            $this->fail('Expected the move to fail.');
        } catch (\RuntimeException) {
            $this->assertSame([], $disk->allFiles());
        }
    }

    public function testRequireArrayOrForgetReturnsNullForMissingFile(): void
    {
        $disk = Storage::fake();

        $this->assertNull(Utils::requireArrayOrForget(new Filesystem(), $disk->path('file.php')));
    }

    public function testRequireArrayOrForgetReturnsArray(): void
    {
        $disk = Storage::fake();
        $disk->put('file.php', "<?php return ['foo' => 1];");

        $this->assertSame(['foo' => 1], Utils::requireArrayOrForget(new Filesystem(), $disk->path('file.php')));
        $this->assertTrue($disk->exists('file.php'));
    }

    /** @dataProvider invalidArrayFiles */
    #[DataProvider('invalidArrayFiles')]
    public function testRequireArrayOrForgetDeletesInvalidFile(string $contents): void
    {
        $disk = Storage::fake();
        $disk->put('file.php', $contents);

        $this->assertNull(Utils::requireArrayOrForget(new Filesystem(), $disk->path('file.php')));
        $this->assertFalse($disk->exists('file.php'));
    }

    /** @return iterable<string, array{string}> */
    public static function invalidArrayFiles(): iterable
    {
        yield 'empty' => [''];
        yield 'returns no array' => ['<?php return 1;'];
        yield 'truncated' => ["<?php return array (\n  'kind' =>"];
    }

    public function testRequireArrayOrForgetReturnsNullWhenFileVanishesBeforeRequire(): void
    {
        $filesystem = new class extends Filesystem {
            public function exists($path): bool
            {
                return true;
            }
        };

        $disk = Storage::fake();

        $this->assertNull(Utils::requireArrayOrForget($filesystem, $disk->path('file.php')));
    }
}
