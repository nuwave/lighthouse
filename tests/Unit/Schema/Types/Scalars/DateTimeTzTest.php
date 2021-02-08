<?php

namespace Tests\Unit\Schema\Types\Scalars;

use Nuwave\Lighthouse\Schema\Types\Scalars\DateScalar;
use Nuwave\Lighthouse\Schema\Types\Scalars\DateTimeTz;

class DateTimeTzTest extends DateScalarTest
{
    protected function scalarInstance(): DateScalar
    {
        return new DateTimeTz();
    }

    public function validDates(): array
    {
        return [
            ['2020-04-20T16:20:04+04:00'],
            ['2020-04-20T16:20:04Z'],
        ];
    }

    public function canonicalizeDates(): array
    {
        return [
            ['2020-4-20T16:20:04+04:0', '2020-04-20T16:20:04+04:00'],
        ];
    }
}
