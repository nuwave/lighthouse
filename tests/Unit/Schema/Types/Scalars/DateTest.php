<?php

namespace Tests\Unit\Schema\Types\Scalars;

use Carbon\Carbon;
use GraphQL\Error\Error;
use GraphQL\Error\InvariantViolation;
use GraphQL\Language\AST\IntValueNode;
use GraphQL\Language\AST\StringValueNode;
use Nuwave\Lighthouse\Schema\Types\Scalars\Date;
use Tests\TestCase;

class DateTest extends TestCase
{
    /**
     * @dataProvider invalidDateValues
     *
     * @param  mixed  $value
     * @return void
     */
    public function testThrowsIfSerializingNonString($value): void
    {
        $this->expectException(InvariantViolation::class);

        (new Date)->serialize($value);
    }

    /**
     * @dataProvider invalidDateValues
     *
     * @param  mixed  $value
     * @return void
     */
    public function testThrowsIfParseValueNonString($value): void
    {
        $this->expectException(Error::class);

        (new Date)->parseValue($value);
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
        $date = '2018-10-01';
        $this->assertEquals(
            (new Carbon($date))->startOfDay(),
            (new Date)->parseValue($date)
        );
    }

    public function testParsesLiteral(): void
    {
        $dateLiteral = new StringValueNode(
            ['value' => '2018-10-01']
        );
        $result = (new Date)->parseLiteral($dateLiteral);

        $this->assertSame(
            $dateLiteral->value,
            $result->toDateString()
        );
    }

    public function testThrowsIfParseLiteralNonString(): void
    {
        $this->expectException(Error::class);

        (new Date)->parseLiteral(
            new IntValueNode([])
        );
    }

    public function testSerializesCarbonInstance(): void
    {
        $now = now();
        $result = (new Date)->serialize($now);

        $this->assertSame(
            $now->toDateString(),
            $result
        );
    }

    public function testSerializesValidDateString(): void
    {
        $date = '2018-10-01';
        $result = (new Date)->serialize($date);

        $this->assertSame(
            $date,
            $result
        );
    }
}
