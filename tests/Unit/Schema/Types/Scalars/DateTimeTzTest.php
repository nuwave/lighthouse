<?php declare(strict_types=1);

namespace Tests\Unit\Schema\Types\Scalars;

use Nuwave\Lighthouse\Schema\Types\Scalars\DateScalar;
use Nuwave\Lighthouse\Schema\Types\Scalars\DateTimeTz;

final class DateTimeTzTest extends DateScalarTestBase
{
    protected function scalarInstance(): DateScalar
    {
        return new DateTimeTz();
    }

    public static function validDates(): iterable
    {
        yield ['2020-04-20T16:20:04+04:00'];
        yield ['2020-04-20T16:20:04Z'];
    }

    public static function canonicalizeDates(): iterable
    {
        yield ['2020-4-20T16:20:04+04:0', '2020-04-20T16:20:04+04:00'];
    }
}
