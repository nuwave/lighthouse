<?php

namespace Tests\Unit\GlobalId;

use Nuwave\Lighthouse\GlobalId\GlobalId;
use Nuwave\Lighthouse\GlobalId\GlobalIdException;
use Tests\TestCase;

final class GlobalIdTest extends TestCase
{
    /**
     * @var \Nuwave\Lighthouse\GlobalId\GlobalId
     */
    protected $globalIdResolver;

    public function setUp(): void
    {
        parent::setUp();

        $this->globalIdResolver = new GlobalId();
    }

    public function testHandleGlobalIds(): void
    {
        $globalId = $this->globalIdResolver->encode('User', 'asdf');
        $idParts = $this->globalIdResolver->decode($globalId);

        $this->assertSame(['User', 'asdf'], $idParts);
    }

    public function testDecodeJustTheId(): void
    {
        $globalId = $this->globalIdResolver->encode('User', 123);

        $this->assertSame('123', $this->globalIdResolver->decodeID($globalId));
    }

    public function testDecodeJustTheType(): void
    {
        $globalId = $this->globalIdResolver->encode('User', 123);

        $this->assertSame('User', $this->globalIdResolver->decodeType($globalId));
    }

    /**
     * @dataProvider provideInvalidGlobalIds
     */
    public function testThrowsOnInvalidGlobalIds(string $invalidGlobalId): void
    {
        $this->expectException(GlobalIdException::class);
        $this->globalIdResolver->decode($invalidGlobalId);
    }

    /**
     * @return array<int, array{0: string}>
     */
    public function provideInvalidGlobalIds(): array
    {
        return [
            ['foo'],
            ['foo:bar:baz'],
            ['foo::baz'],
            [':::'],
        ];
    }
}
