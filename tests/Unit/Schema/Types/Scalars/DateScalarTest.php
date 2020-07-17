<?php

namespace Tests\Unit\Schema\Types\Scalars;

use Carbon\Carbon;
use GraphQL\Error\Error;
use GraphQL\Error\InvariantViolation;
use GraphQL\Language\AST\IntValueNode;
use GraphQL\Language\AST\StringValueNode;
use Nuwave\Lighthouse\Schema\Types\Scalars\DateScalar;
use Tests\TestCase;

abstract class DateScalarTest extends TestCase
{
    /**
     * @dataProvider invalidDateValues
     *
     * @param  mixed  $value An invalid value for a date
     */
    public function testThrowsIfSerializingInvalidDates($value): void
    {
        $this->expectException(InvariantViolation::class);

        $this->scalarInstance()->serialize($value);
    }

    /**
     * @dataProvider invalidDateValues
     *
     * @param  mixed  $value An invalid value for a date
     */
    public function testThrowsIfParseValueInvalidDate($value): void
    {
        $this->expectException(Error::class);

        $this->scalarInstance()->parseValue($value);
    }

    /**
     * Those values should fail passing as a date.
     *
     * @return mixed[]
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
            Carbon::class,
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

        $this->assertInstanceOf(Carbon::class, $parsed);
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
        $now = now();
        $result = $this->scalarInstance()->serialize($now);

        $this->assertInternalType('string', $result);
    }

    /**
     * @dataProvider canonicalizeDates
     */
    public function testCanonicalizesValidDateString(string $date, string $canonical): void
    {
        $result = $this->scalarInstance()->serialize($date);

        $this->assertSame($canonical, $result);
    }

    abstract protected function scalarInstance(): DateScalar;

    abstract public function validDates(): array;

    abstract public function canonicalizeDates(): array;
}
