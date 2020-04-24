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

    protected function validDate(): string
    {
        return '2020-04-20T16:20:04.000000Z';
    }
}
