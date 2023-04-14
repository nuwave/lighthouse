<?php declare(strict_types=1);

namespace Tests\Unit\Support;

use Nuwave\Lighthouse\Support\Utils;
use Tests\TestCase;

final class UtilsTest extends TestCase
{
    /** @dataProvider nameToEnumValueName */
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
}
