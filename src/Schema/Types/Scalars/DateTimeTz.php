<?php

namespace Nuwave\Lighthouse\Schema\Types\Scalars;

use Carbon\Carbon;

class DateTimeTz extends DateScalar
{
    protected function format(Carbon $carbon): string
    {
        return $carbon->toIso8601String();
    }

    protected function parse($value): Carbon
    {
        return Carbon::createFromFormat(Carbon::ISO8601, $value);
    }
}
