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
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

abstract class DateScalarTestBase extends TestCase
{
    /** @dataProvider invalidDateValues */
    #[DataProvider('invalidDateValues')]
    public function testThrowsIfSerializingInvalidDates(mixed $value): void
    {
        $dateScalar = $this->scalarInstance();

        $this->expectException(InvariantViolation::class);
        $dateScalar->serialize($value);
    }

    /** @dataProvider invalidDateValues */
    #[DataProvider('invalidDateValues')]
    public function testThrowsIfParseValueInvalidDate(mixed $value): void
    {
        $dateScalar = $this->scalarInstance();

        $this->expectException(Error::class);
        $dateScalar->parseValue($value);
    }

    public function testReturnsIlluminateSupportCarbonAsIs(): void
    {
        $original = IlluminateCarbon::now();
        $parsed = $this->scalarInstance()->parseValue($original);
        $this->assertSame($original, $parsed);
    }

    /** @dataProvider dateTimeInterfaceInstances */
    #[DataProvider('dateTimeInterfaceInstances')]
    public function testConvertsDateTimeInterfaceToIlluminateSupportCarbon(\DateTimeInterface $original): void
    {
        $parsed = $this->scalarInstance()->parseValue($original);
        $this->assertSame($original->getTimestamp(), $parsed->getTimestamp());
        $this->assertTrue($parsed->isValid());
    }

    /** @return iterable<array{\DateTimeInterface}> */
    public static function dateTimeInterfaceInstances(): iterable
    {
        yield 'native DateTime' => [new \DateTime()]; // @phpstan-ignore-line theCodingMachineSafe.class (we want to use the native DateTime to ensure it specifically works)
        yield 'safe DateTime' => [new \Safe\DateTime()];

        yield 'native DateTimeImmutable' => [new \DateTimeImmutable()]; // @phpstan-ignore-line theCodingMachineSafe.class (we want to use the native DateTime to ensure it specifically works)
        yield 'safe DateTimeImmutable' => [new \Safe\DateTimeImmutable()];

        yield 'Carbon' => [CarbonCarbon::now()];
        yield 'CarbonImmutable' => [CarbonCarbonImmutable::now()];
    }

    /**
     * Those values should fail passing as a date.
     *
     * @return iterable<array{mixed}>
     */
    public static function invalidDateValues(): iterable
    {
        yield [1];
        yield ['rolf'];
        yield [null];
        yield [''];
    }

    /** @dataProvider validDates */
    #[DataProvider('validDates')]
    public function testParsesValueString(string $date): void
    {
        $this->assertTrue(
            $this->scalarInstance()->parseValue($date)->isValid(),
        );
    }

    /** @dataProvider validDates */
    #[DataProvider('validDates')]
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
    #[DataProvider('canonicalizeDates')]
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
