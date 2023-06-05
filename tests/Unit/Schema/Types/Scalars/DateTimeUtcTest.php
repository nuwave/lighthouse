<?php declare(strict_types=1);

namespace Tests\Unit\Schema\Types\Scalars;

use Nuwave\Lighthouse\Schema\Types\Scalars\DateScalar;
use Nuwave\Lighthouse\Schema\Types\Scalars\DateTimeUtc;

final class DateTimeUtcTest extends DateScalarTestBase
{
    protected function scalarInstance(): DateScalar
    {
        return new DateTimeUtc();
    }

    public static function validDates(): iterable
    {
        yield ['2020-04-20T16:20:04.000000Z'];
        yield ['2020-04-20T16:20:04.000Z'];
        yield ['2020-04-20T16:20:04.0Z'];
    }

    public static function canonicalizeDates(): iterable
    {
        yield ['2020-04-20T16:20:04.123Z', '2020-04-20T16:20:04.123000Z'];
    }
}
