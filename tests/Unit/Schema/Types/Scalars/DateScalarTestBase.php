<?php declare(strict_types=1);

namespace Tests\Unit\Schema\Types\Scalars;

use Carbon\Carbon as CarbonCarbon;
use Carbon\CarbonImmutable as CarbonCarbonImmutable;
use GraphQL\Error\Error;
use GraphQL\Error\InvariantViolation;
use GraphQL\Language\AST\IntValueNode;
use GraphQL\Language\AST\StringValueNode;
use Illuminate\Support\Carbon as IlluminateCarbon;
use Nuwave\Lighthouse\Schema\Types\Scalars\DateScalar;
use Tests\TestCase;

abstract class DateScalarTestBase extends TestCase
{
    /** @dataProvider invalidDateValues */
    public function testThrowsIfSerializingInvalidDates(mixed $value): void
    {
        $dateScalar = $this->scalarInstance();

        $this->expectException(InvariantViolation::class);
        $dateScalar->serialize($value);
    }

    /** @dataProvider invalidDateValues */
    public function testThrowsIfParseValueInvalidDate(mixed $value): void
    {
        $dateScalar = $this->scalarInstance();

        $this->expectException(Error::class);
        $dateScalar->parseValue($value);
    }

    public function testConvertsCarbonCarbonToIlluminateSupportCarbon(): void
    {
        $this->assertTrue(
            $this->scalarInstance()->parseValue(CarbonCarbon::now())->isValid(),
        );
    }

    public function testConvertsCarbonCarbonImmutableToIlluminateSupportCarbon(): void
    {
        $this->assertTrue(
            $this->scalarInstance()->parseValue(CarbonCarbonImmutable::now())->isValid(),
        );
    }

    /**
     * Those values should fail passing as a date.
     *
     * @return array<array<mixed>>
     */
    public static function invalidDateValues(): array
    {
        return [
            [1],
            ['rolf'],
            [null],
            [''],
        ];
    }

    /** @dataProvider validDates */
    public function testParsesValueString(string $date): void
    {
        $this->assertTrue(
            $this->scalarInstance()->parseValue($date)->isValid(),
        );
    }

    /** @dataProvider validDates */
    public function testParsesLiteral(string $date): void
    {
        $dateLiteral = new StringValueNode(
            ['value' => $date],
        );
        $parsed = $this->scalarInstance()->parseLiteral($dateLiteral);

        $this->assertTrue($parsed->isValid());
    }

    public function testThrowsIfParseLiteralNonString(): void
    {
        $this->expectException(Error::class);

        $this->scalarInstance()->parseLiteral(
            new IntValueNode([]),
        );
    }

    public function testSerializesCarbonInstance(): void
    {
        $now = IlluminateCarbon::now();
        $this->scalarInstance()->serialize($now);

        self::expectNotToPerformAssertions();
    }

    /** @dataProvider canonicalizeDates */
    public function testCanonicalizesValidDateString(string $date, string $canonical): void
    {
        $result = $this->scalarInstance()->serialize($date);

        $this->assertSame($canonical, $result);
    }

    /** The specific instance under test. */
    abstract protected function scalarInstance(): DateScalar;

    /**
     * Data provider for valid date strings.
     *
     * @return iterable<array<string>>
     */
    abstract public static function validDates(): iterable;

    /**
     * Data provider with pairs of dates:
     * 1. A valid representation of the date
     * 2. The canonical representation of the date.
     *
     * @return iterable<array{string, string}>
     */
    abstract public static function canonicalizeDates(): iterable;
}
