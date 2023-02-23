<?php declare(strict_types=1);

namespace Nuwave\Lighthouse\Schema\Types\Scalars;

use Illuminate\Support\Carbon;

class DateTime extends DateScalar
{
    protected function format(Carbon $carbon): string
    {
        return $carbon->toDateTimeString();
    }

    protected function parse(mixed $value): Carbon
    {
        // @phpstan-ignore-next-line We know the format to be good, so this can never return `false`
        return Carbon::createFromFormat(Carbon::DEFAULT_TO_STRING_FORMAT, $value);
    }
}
