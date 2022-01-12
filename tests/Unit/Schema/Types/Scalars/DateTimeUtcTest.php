<?php

namespace Tests\Unit\Schema\Types\Scalars;

use Nuwave\Lighthouse\Schema\Types\Scalars\DateScalar;
use Nuwave\Lighthouse\Schema\Types\Scalars\DateTimeUtc;

class DateTimeUtcTest extends DateScalarTest
{
    protected function scalarInstance(): DateScalar
    {
        return new DateTimeUtc();
    }

    public function validDates(): iterable
    {
        return [
            ['2020-04-20T16:20:04.000000Z'],
            ['2020-04-20T16:20:04.000Z'],
            ['2020-04-20T16:20:04.0Z'],
        ];
    }

    public function canonicalizeDates(): iterable
    {
        return [
            ['2020-04-20T16:20:04.123Z', '2020-04-20T16:20:04.123000Z'],
        ];
    }
}
