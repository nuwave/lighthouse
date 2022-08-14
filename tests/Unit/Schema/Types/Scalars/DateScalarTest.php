<?php

namespace Tests\Unit\Schema\Types\Scalars;

use Carbon\Carbon;
use Carbon\Carbon as CarbonCarbon;
use Carbon\CarbonImmutable as CarbonCarbonImmutable;
use GraphQL\Error\Error;
use GraphQL\Error\InvariantViolation;
use GraphQL\Language\AST\IntValueNode;
use GraphQL\Language\AST\StringValueNode;
use Illuminate\Support\Carbon as IlluminateCarbon;
use Nuwave\Lighthouse\Schema\Types\Scalars\DateScalar;
use Tests\TestCase;

abstract class DateScalarTest extends TestCase
{
    /**
     * @dataProvider invalidDateValues
     *
     * @param  mixed  $value  An invalid value for a date
     */
    public function testThrowsIfSerializingInvalidDates($value): void
    {
        $this->expectException(InvariantViolation::class);

        $this->scalarInstance()->serialize($value);
    }

    /**
     * @dataProvider invalidDateValues
     *
     * @param  mixed  $value  An invalid value for a date
     */
    public function testThrowsIfParseValueInvalidDate($value): void
    {
        $this->expectException(Error::class);

        $this->scalarInstance()->parseValue($value);
    }

    public function testConvertsCarbonCarbonToIlluminateSupportCarbon(): void
    {
        $this->assertInstanceOf(
            IlluminateCarbon::class,
            $this->scalarInstance()->parseValue(CarbonCarbon::now())
        );
    }

    public function testConvertsCarbonCarbonImmutableToIlluminateSupportCarbon(): void
    {
        // TODO remove when we stop supporting Laravel 5.7
        if (! class_exists('\Carbon\CarbonImmutable')) {
            $this->markTestSkipped('CarbonImmutable is not available with older Laravel versions');
        }

        $this->assertInstanceOf(
            IlluminateCarbon::class,
            $this->scalarInstance()->parseValue(CarbonCarbonImmutable::now())
        );
    }

    /**
     * Those values should fail passing as a date.
     *
     * @return array<array<mixed>>
     */
    public function invalidDateValues(): array
    {
        return [
            [1],
            ['rolf'],
            [null],
            [''],
        ];
    }

    /**
     * @dataProvider validDates
     */
    public function testParsesValueString(string $date): void
    {
        $this->assertInstanceOf(
            IlluminateCarbon::class,
            $this->scalarInstance()->parseValue($date)
        );
    }

    /**
     * @dataProvider validDates
     */
    public function testParsesLiteral(string $date): void
    {
        $dateLiteral = new StringValueNode(
            ['value' => $date]
        );
        $parsed = $this->scalarInstance()->parseLiteral($dateLiteral);

        $this->assertInstanceOf(IlluminateCarbon::class, $parsed);
    }

    public function testThrowsIfParseLiteralNonString(): void
    {
        $this->expectException(Error::class);

        $this->scalarInstance()->parseLiteral(
            new IntValueNode([])
        );
    }

    public function testSerializesCarbonInstance(): void
    {
        if (! method_exists(Carbon::class, 'toJSON')) {
            $this->markTestSkipped('toJSON is not in older Carbon versions');
        }

        $now = IlluminateCarbon::now();
        $result = $this->scalarInstance()->serialize($now);

        // TODO use native assertIsString when upgrading PHPUnit
        $this->assertTrue(is_string($result));
    }

    /**
     * @dataProvider canonicalizeDates
     */
    public function testCanonicalizesValidDateString(string $date, string $canonical): void
    {
        if (! method_exists(Carbon::class, 'createFromIsoFormat')) {
            $this->markTestSkipped('createFromIsoFormat is not in older Carbon versions');
        }

        $result = $this->scalarInstance()->serialize($date);

        $this->assertSame($canonical, $result);
    }

    /**
     * The specific instance under test.
     */
    abstract protected function scalarInstance(): DateScalar;

    /**
     * Data provider for valid date strings.
     *
     * @return iterable<array<string>>
     */
    abstract public function validDates(): iterable;

    /**
     * Data provider with pairs of dates:
     * 1. A valid representation of the date
     * 2. The canonical representation of the date.
     *
     * @return iterable<array{string, string}>
     */
    abstract public function canonicalizeDates(): iterable;
}
