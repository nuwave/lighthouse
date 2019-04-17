<?php

namespace Tests\Unit\Execution\Utils;

use Tests\TestCase;
use Nuwave\Lighthouse\Support\Contracts\GlobalId;

class GlobalIdTest extends TestCase
{
    /**
     * @test
     */
    public function itCanHandleGlobalIds(): void
    {
        $globalId = app(GlobalId::class)->encode('User', 'asdf');
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
