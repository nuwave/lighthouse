<?php declare(strict_types=1);

namespace Tests\Unit\Schema\Types\Scalars;

use Nuwave\Lighthouse\Schema\Types\Scalars\DateScalar;
use Nuwave\Lighthouse\Schema\Types\Scalars\DateTime;

final class DateTimeTest extends DateScalarTestBase
{
    protected function scalarInstance(): DateScalar
    {
        return new DateTime();
    }

    public static function validDates(): iterable
    {
        yield ['2020-04-20 23:51:15'];
    }

    public static function canonicalizeDates(): iterable
    {
        yield ['2020-4-20 23:51:15', '2020-04-20 23:51:15'];
    }
}
