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
     */
    public function testThrowsIfSerializingInvalidDates($value): void
    {
        $this->expectException(InvariantViolation::class);

        $this->dateScalar()->serialize($value);
    }

    /**
     * @dataProvider invalidDateValues
     */
    public function testThrowsIfParseValueInvalidDate($value): void
    {
        $this->expectException(Error::class);

        $this->dateScalar()->parseValue($value);
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

    public function testParsesValueString(): void
    {
        $this->assertInstanceOf(
            Carbon::class,
            $this->dateScalar()->parseValue($this->validDate())
        );
    }

    public function testParsesLiteral(): void
    {
        $dateLiteral = new StringValueNode(
            ['value' => $this->validDate()]
        );
        $parsed = $this->dateScalar()->parseLiteral($dateLiteral);

        $this->assertInstanceOf(Carbon::class, $parsed);
    }

    public function testThrowsIfParseLiteralNonString(): void
    {
        $this->expectException(Error::class);

        $this->dateScalar()->parseLiteral(
            new IntValueNode([])
        );
    }

    public function testSerializesCarbonInstance(): void
    {
        $now = now();
        $result = $this->dateScalar()->serialize($now);

        $this->assertIsString($result);
    }

    public function testSerializesValidDateString(): void
    {
        $date = $this->validDate();
        $result = $this->dateScalar()->serialize($date);

        $this->assertSame($date, $result);
    }

    abstract protected function dateScalar(): DateScalar;

    abstract protected function validDate(): string;
}
