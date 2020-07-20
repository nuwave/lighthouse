<?php

namespace Nuwave\Lighthouse\Schema\Types\Scalars;

use Carbon\Carbon;

class Date extends DateScalar
{
    protected function format(Carbon $carbon): string
    {
        return $carbon->toDateString();
    }

    protected function parse($value): Carbon
    {
        return Carbon::createFromFormat('Y-m-d', $value)->startOfDay();
    }
}
