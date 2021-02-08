<?php

namespace Tests\Unit\Schema\Types\Scalars;

use Nuwave\Lighthouse\Schema\Types\Scalars\DateScalar;
use Nuwave\Lighthouse\Schema\Types\Scalars\DateTimeUtc;
use Nuwave\Lighthouse\Support\AppVersion;

class DateTimeUtcTest extends DateScalarTest
{
    public function setUp(): void
    {
        parent::setUp();

        if (AppVersion::below(5.8)) {
            $this->markTestSkipped('This only works with Illuminate\Support\Carbon::createFromIsoFormat().');
        }
    }

    protected function scalarInstance(): DateScalar
    {
        return new DateTimeUtc();
    }

    public function validDates(): array
    {
        return [
            ['2020-04-20T16:20:04.000000Z'],
            ['2020-04-20T16:20:04.000Z'],
            ['2020-04-20T16:20:04.0Z'],
        ];
    }

    public function canonicalizeDates(): array
    {
        return [
            ['2020-04-20T16:20:04.123Z', '2020-04-20T16:20:04.123000Z'],
        ];
    }
}
