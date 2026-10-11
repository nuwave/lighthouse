<?php declare(strict_types=1);

namespace Tests\Unit\GlobalId;

use Nuwave\Lighthouse\GlobalId\Base64GlobalId;
use Nuwave\Lighthouse\GlobalId\GlobalIdException;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

final class Base64GlobalIdTest extends TestCase
{
    protected Base64GlobalId $base64GlobalId;

    protected function setUp(): void
    {
        parent::setUp();

        $this->base64GlobalId = new Base64GlobalId();
    }

    public function testHandleGlobalIds(): void
    {
        $globalId = $this->base64GlobalId->encode('User', 'asdf');
        $idParts = $this->base64GlobalId->decode($globalId);

        $this->assertSame(['User', 'asdf'], $idParts);
    }

    public function testDecodeJustTheId(): void
    {
        $globalId = $this->base64GlobalId->encode('User', 123);

        $this->assertSame('123', $this->base64GlobalId->decodeID($globalId));
    }

    public function testDecodeJustTheType(): void
    {
        $globalId = $this->base64GlobalId->encode('User', 123);

        $this->assertSame('User', $this->base64GlobalId->decodeType($globalId));
    }

    /** @dataProvider provideInvalidGlobalIds */
    #[DataProvider('provideInvalidGlobalIds')]
    public function testThrowsOnInvalidGlobalIds(string $invalidGlobalId): void
    {
        $this->expectException(GlobalIdException::class);
        $this->base64GlobalId->decode($invalidGlobalId);
    }

    /** @return iterable<array{string}> */
    public static function provideInvalidGlobalIds(): iterable
    {
        yield ['foo'];
        yield ['foo:bar:baz'];
        yield ['foo::baz'];
        yield [':::'];
    }
}
