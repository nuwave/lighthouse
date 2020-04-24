<?php

namespace Tests\Unit\Schema\Types\Scalars;

use Nuwave\Lighthouse\Schema\Types\Scalars\DateScalar;
use Nuwave\Lighthouse\Schema\Types\Scalars\DateTime;

class DateTimeTest extends DateScalarTest
{
    protected function scalarInstance(): DateScalar
    {
        return new DateTime();
    }

    public function validDates(): array
    {
        return [
            ['2020-04-20 23:51:15'],
        ];
    }

    public function canonicalizeDates(): array
    {
        return [
            ['2020-4-20 23:51:15', '2020-04-20 23:51:15'],
        ];
    }
}
