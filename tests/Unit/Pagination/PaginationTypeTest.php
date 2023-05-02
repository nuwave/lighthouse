<?php declare(strict_types=1);

namespace Tests\Unit\Pagination;

use Nuwave\Lighthouse\Exceptions\DefinitionException;
use Nuwave\Lighthouse\Pagination\PaginationType;
use Tests\TestCase;

final class PaginationTypeTest extends TestCase
{
    /**
     * @dataProvider invalidPaginationTypes
     *
     * @param  string  $type An invalid type
     */
    public function testThrowsExceptionForUnsupportedTypes(string $type): void
    {
        self::expectExceptionObject(new DefinitionException("Found invalid pagination type: {$type}"));

        new PaginationType($type);
    }

    /** @return iterable<array{string}> */
    public static function invalidPaginationTypes(): iterable
    {
        yield ['paginator'];
        yield ['simple'];
        yield ['connection'];
        yield ['relay'];
        yield ['default'];
        yield ['foo'];
    }
}
