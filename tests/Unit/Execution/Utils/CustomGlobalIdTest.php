<?php

namespace Tests\Unit\Execution\Utils;

use Tests\TestCase;
use Nuwave\Lighthouse\Support\Contracts\GlobalId;
use Nuwave\Lighthouse\Execution\Utils\GlobalId as BaseGlobalId;

class CustomGlobalIdTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        $this->app->bind(GlobalId::class, CustomGlobalId::class);
    }

    protected function tearDown(): void
    {
        $this->app->bind(GlobalId::class, BaseGlobalId::class);

        parent::tearDown();
    }

    /**
     * @test
     */
    public function itCanHandleGlobalIds(): void
    {
        $globalId = app(GlobalId::class)->encode('User', 'asdf');

        $this->assertSame('gid://myapp/User/asdf', $globalId);

        $idParts = app(GlobalId::class)->decode($globalId);

        $this->assertSame(['User', 'asdf'], $idParts);
    }

    /**
     * @test
     */
    public function itCanDecodeJustTheId(): void
    {
        $globalId = app(GlobalId::class)->encode('User', 123);

        $this->assertSame('123', app(GlobalId::class)->decodeID($globalId));
    }

    /**
     * @test
     */
    public function itCanDecodeJustTheType(): void
    {
        $globalId = app(GlobalId::class)->encode('User', 123);

        $this->assertSame('User', app(GlobalId::class)->decodeType($globalId));
    }
}

class CustomGlobalId implements GlobalId
{
    public function encode(string $type, $id): string
    {
        return "gid://myapp/$type/$id";
    }

    public function decodeID(string $globalID): string
    {
        [$type, $id] = self::decode($globalID);

        return $id;
    }

    public function decode(string $globalID): array
    {
        preg_match('/gid:\/\/myapp\/(.*)\/(.*)/', $globalID, $matches);

        return [
            $matches[1], $matches[2],
        ];
    }

    public function decodeType(string $globalID): string
    {
        [$type, $id] = self::decode($globalID);

        return $type;
    }
}
