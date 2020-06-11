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

    protected function validDate(): string
    {
        return '2020-04-20';
    }
}
