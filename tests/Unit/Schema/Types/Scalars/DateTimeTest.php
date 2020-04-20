<?php

namespace Tests\Unit\Schema\Types\Scalars;

use Nuwave\Lighthouse\Schema\Types\Scalars\DateScalar;
use Nuwave\Lighthouse\Schema\Types\Scalars\DateTime;

class DateTimeTest extends DateScalarTest
{
    protected function dateScalar(): DateScalar
    {
        return new DateTime();
    }

    protected function validDate(): string
    {
        return '2020-04-20 23:51:15';
    }
}
