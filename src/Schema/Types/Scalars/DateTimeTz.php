<?php

namespace Nuwave\Lighthouse\Schema\Types\Scalars;

use Carbon\Carbon;

class DateTimeTz extends DateScalar
{
    protected function format(Carbon $carbon): string
    {
        return $carbon->toJSON();
    }

    protected function parse($value): Carbon
    {
        return Carbon::createFromIsoFormat('YYYY-MM-DDTHH:mm:ss.SSSSSSZ', $value);
    }
}
