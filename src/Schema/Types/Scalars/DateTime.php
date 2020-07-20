<?php

namespace Nuwave\Lighthouse\Schema\Types\Scalars;

use Carbon\Carbon;

class DateTime extends DateScalar
{
    protected function format(Carbon $carbon): string
    {
        return $carbon->toDateTimeString();
    }

    protected function parse($value): Carbon
    {
        return Carbon::createFromFormat(Carbon::DEFAULT_TO_STRING_FORMAT, $value);
    }
}
