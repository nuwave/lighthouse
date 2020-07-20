<?php

namespace Tests\Unit\Schema\Types\Scalars;

use Nuwave\Lighthouse\Schema\Types\Scalars\Date;
use Nuwave\Lighthouse\Schema\Types\Scalars\DateScalar;

class DateTest extends DateScalarTest
{
    protected function scalarInstance(): DateScalar
    {
        return new Date();
    }

    public function validDates(): array
    {
        return [
            ['2020-04-20'],
        ];
    }

    public function canonicalizeDates(): array
    {
        return [
            ['2020-4-20', '2020-04-20'],
        ];
    }
}
