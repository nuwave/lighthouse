<?php

namespace Tests\Unit\Execution\Utils;

use Tests\TestCase;
use Nuwave\Lighthouse\Execution\Utils\GlobalId;

class GlobalIdTest extends TestCase
{
    /**
     * @var \Nuwave\Lighthouse\Execution\Utils\GlobalId
     */
    private $globalIdResolver;

    protected function setUp():void
    {
        parent::setUp();

        $this->globalIdResolver = new GlobalId;
    }

    /**
     * @test
     */
    public function itCanHandleGlobalIds(): void
    {
        $globalId = $this->globalIdResolver->encode('User', 'asdf');
        $idParts = $this->globalIdResolver->decode($globalId);

        $this->assertSame(['User', 'asdf'], $idParts);
    }

    /**
     * @test
     */
    public function itCanDecodeJustTheId(): void
    {
        $globalId = $this->globalIdResolver->encode('User', 123);

        $this->assertSame('123', $this->globalIdResolver->decodeID($globalId));
    }

    /**
     * @test
     */
    public function itCanDecodeJustTheType(): void
    {
        $globalId = $this->globalIdResolver->encode('User', 123);

        $this->assertSame('User', $this->globalIdResolver->decodeType($globalId));
    }
}
