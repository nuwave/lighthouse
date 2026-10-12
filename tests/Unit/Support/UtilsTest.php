<?php declare(strict_types=1);

namespace Tests\Unit\Support;

use Illuminate\Filesystem\Filesystem;
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
        // Simulates a full disk: partial contents are written, then Laravel converts the warning to an exception
        $filesystem = new class extends Filesystem {
            public function put($path, $contents, $lock = false): int|bool
            {
                parent::put($path, substr($contents, 0, 3));

                throw new \ErrorException('file_put_contents(): Write failed');
            }
        };

        $directory = sys_get_temp_dir() . '/lighthouse-atomic-put-' . bin2hex(random_bytes(6));
        $filesystem->makeDirectory($directory);

        try {
            Utils::atomicPut(filesystem: $filesystem, path: "{$directory}/file.php", contents: '<?php return [];');
            $this->fail('Expected the write to fail.');
        } catch (\ErrorException) {
            $this->assertSame([], $filesystem->files($directory));
        } finally {
            $filesystem->deleteDirectory($directory);
        }
    }
}
