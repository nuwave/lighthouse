<?php

namespace Nuwave\Lighthouse\Schema\Types\Scalars;

use Illuminate\Support\Carbon;

class DateTimeTz extends DateScalar
{
    protected function format(Carbon $carbon): string
    {
        return $carbon->toIso8601String();
    }

    protected function parse($value): Carbon
    {
        // @phpstan-ignore-next-line We know the format to be good, so this can never return `false`
        return Carbon::createFromFormat(
            // https://www.php.net/manual/en/class.datetimeinterface.php#datetime.constants.iso8601
            Carbon::ATOM,
            $value
        );
    }
}
