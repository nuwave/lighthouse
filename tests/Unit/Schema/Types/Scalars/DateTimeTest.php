<?php

namespace Tests\Unit\Schema\Types\Scalars;

use Carbon\Carbon;
use Tests\TestCase;
use GraphQL\Error\Error;
use GraphQL\Error\InvariantViolation;
use GraphQL\Language\AST\IntValueNode;
use GraphQL\Language\AST\StringValueNode;
use Nuwave\Lighthouse\Schema\Types\Scalars\DateTime;

class DateTimeTest extends TestCase
{
    /**
     * @test
     * @dataProvider invalidDateTimeValues
     *
     * @param  mixed  $value
     */
    public function itThrowsIfSerializingNonString($value): void
    {
        $this->expectException(InvariantViolation::class);

        (new DateTime)->serialize($value);
    }

    /**
     * @test
     * @dataProvider invalidDateTimeValues
     *
     * @param  mixed  $value
     */
    public function itThrowsIfParseValueNonString($value): void
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
            [new class {
            }],
            [null],
            [''],
        ];
    }

    /**
     * @test
     */
    public function itParsesValueString(): void
    {
        $date = '2018-10-01 12:45:01';
        $this->assertEquals(
            (new Carbon($date))->toDateTimeString(),
            (new DateTime)->parseValue($date)
        );
    }

    /**
     * @test
     */
    public function itParsesLiteral(): void
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

    /**
     * @test
     */
    public function itThrowsIfParseLiteralNonString(): void
    {
        $this->expectException(Error::class);

        (new DateTime)->parseLiteral(
            new IntValueNode([])
        );
    }

    /**
     * @test
     */
    public function itSerializesCarbonInstance(): void
    {
        $now = now();
        $result = (new DateTime)->serialize($now);

        $this->assertSame(
            $now->toDateTimeString(),
            $result
        );
    }

    /**
     * @test
     */
    public function itSerializesValidDateTimeString(): void
    {
        $date = '2018-10-01 12:45:01';
        $result = (new DateTime)->serialize($date);

        $this->assertSame(
            $date,
            $result
        );
    }
}
