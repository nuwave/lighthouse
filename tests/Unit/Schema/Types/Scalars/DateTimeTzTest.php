<?php

namespace Tests\Unit\Schema\Types\Scalars;

use Nuwave\Lighthouse\Schema\Types\Scalars\DateScalar;
use Nuwave\Lighthouse\Schema\Types\Scalars\DateTimeTz;

final class DateTimeTzTest extends DateScalarTest
{
    protected function scalarInstance(): DateScalar
    {
        return new DateTimeTz();
    }

    public function validDates(): iterable
    {
        return [
            ['2020-04-20T16:20:04+04:00'],
            ['2020-04-20T16:20:04Z'],
        ];
    }

    public function canonicalizeDates(): iterable
    {
        return [
            ['2020-4-20T16:20:04+04:0', '2020-04-20T16:20:04+04:00'],
        ];
    }
}
