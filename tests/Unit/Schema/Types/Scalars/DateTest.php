<?php declare(strict_types=1);

namespace Tests\Unit\Schema\Types\Scalars;

use Nuwave\Lighthouse\Schema\Types\Scalars\Date;
use Nuwave\Lighthouse\Schema\Types\Scalars\DateScalar;

final class DateTest extends DateScalarTestBase
{
    protected function scalarInstance(): DateScalar
    {
        return new Date();
    }

    public static function validDates(): iterable
    {
        yield ['2020-04-20'];
    }

    public static function canonicalizeDates(): iterable
    {
        yield ['2020-4-20', '2020-04-20'];
    }
}
