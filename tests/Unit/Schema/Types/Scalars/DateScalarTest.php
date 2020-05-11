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
     * @param  mixed  $value
     */
    public function testThrowsIfSerializingInvalidDates($value): void
    {
        $this->expectException(InvariantViolation::class);

        $this->scalarInstance()->serialize($value);
    }

    /**
     * @dataProvider invalidDateValues
     * @param  mixed  $value
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

    public function testParsesValueString(): void
    {
        $this->assertInstanceOf(
            Carbon::class,
            $this->scalarInstance()->parseValue($this->validDate())
        );
    }

    public function testParsesLiteral(): void
    {
        $dateLiteral = new StringValueNode(
            ['value' => $this->validDate()]
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

    public function testSerializesValidDateString(): void
    {
        $date = $this->validDate();
        $result = $this->scalarInstance()->serialize($date);

        $this->assertSame($date, $result);
    }

    abstract protected function scalarInstance(): DateScalar;

    abstract protected function validDate(): string;
}
