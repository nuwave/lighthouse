<?php

namespace Tests\Unit\Execution\Utils;

use Tests\TestCase;
use Nuwave\Lighthouse\Execution\Utils\GlobalId;

class GlobalIdTest extends TestCase
{
    /**
     * @test
     */
    public function itCanHandleGlobalIds()
    {
        $globalId = GlobalId::encode('User', 'asdf');
        $idParts = GlobalId::decode($globalId);

        $this->assertSame(['User', 'asdf'], $idParts);
    }

    /**
     * @test
     */
    public function itCanDecodeJustTheId()
    {
        $globalId = GlobalId::encode('User', 123);

        $this->assertSame('123', GlobalId::decodeID($globalId));
    }

    /**
     * @test
     */
    public function itCanDecodeJustTheType()
    {
        $globalId = GlobalId::encode('User', 123);

        $this->assertSame('User', GlobalId::decodeType($globalId));
    }
}
