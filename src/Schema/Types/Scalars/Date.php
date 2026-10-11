<?php declare(strict_types=1);

namespace Nuwave\Lighthouse\Schema\Types\Scalars;

use Illuminate\Support\Carbon;

class Date extends DateScalar
{
    protected function format(Carbon $carbon): string
    {
        return $carbon->toDateString();
    }

    protected function parse(string $value): Carbon
    {
        // @phpstan-ignore-next-line We know the format to be good, so this can never return `false`
        return Carbon::createFromFormat('Y-m-d', $value)->startOfDay();
    }
}
