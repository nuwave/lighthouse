<?php

namespace Tests\Unit\Schema\Types\Scalars;

use Carbon\Carbon;
use GraphQL\Error\Error;
use GraphQL\Error\InvariantViolation;
use GraphQL\Language\AST\IntValueNode;
use GraphQL\Language\AST\StringValueNode;
use Nuwave\Lighthouse\Schema\Types\Scalars\DateTime;
use Tests\TestCase;

class DateTimeTest extends TestCase
{
    /**
     * @dataProvider invalidDateTimeValues
     *
     * @param  mixed  $value
     * @return void
     */
    public function testThrowsIfSerializingNonString($value): void
    {
        $this->expectException(InvariantViolation::class);

        (new DateTime)->serialize($value);
    }

    /**
     * @dataProvider invalidDateTimeValues
     *
     * @param  mixed  $value
     * @return void
     */
    public function testThrowsIfParseValueNonString($value): void
    {
        $this->expectException(Error::class);

        (new DateTime)->parseValue($value);
    }

    /**
     * Those values should fail passing as a date.
     *
     * @return mixed[]
     */
    public function invalidDateTimeValues(): array
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
        $date = '2018-10-01 12:45:01';
        $this->assertEquals(
            (new Carbon($date))->toDateTimeString(),
            (new DateTime)->parseValue($date)
        );
    }

    public function testParsesLiteral(): void
    {
        $dateLiteral = new StringValueNode(
            ['value' => '2018-10-01 12:45:01']
        );
        $result = (new DateTime)->parseLiteral($dateLiteral);

        $this->assertSame(
            $dateLiteral->value,
            $result->toDateTimeString()
        );
    }

    public function testThrowsIfParseLiteralNonString(): void
    {
        $this->expectException(Error::class);

        (new DateTime)->parseLiteral(
            new IntValueNode([])
        );
    }

    public function testSerializesCarbonInstance(): void
    {
        $now = now();
        $result = (new DateTime)->serialize($now);

        $this->assertSame(
            $now->toDateTimeString(),
            $result
        );
    }

    public function testSerializesValidDateTimeString(): void
    {
        $date = '2018-10-01 12:45:01';
        $result = (new DateTime)->serialize($date);

        $this->assertSame(
            $date,
            $result
        );
    }
}
