<?php

namespace Nuwave\Lighthouse\Schema\Types\Scalars;

use Carbon\Carbon;

/**
 * Only works with Carbon 2.
 */
class DateTimeUtc extends DateScalar
{
    protected function format(Carbon $carbon): string
    {
        return $carbon->toJSON();
    }

    protected function parse($value): Carbon
    {
        // @phpstan-ignore-next-line We know the format to be good, so this can never return `false`
        return Carbon::createFromIsoFormat('YYYY-MM-DDTHH:mm:ss.SSSSSSZ', $value);
    }
}
