<?php

namespace Tests\Unit\Execution\Utils;

use Nuwave\Lighthouse\Execution\Utils\GlobalId;
use Tests\TestCase;

class GlobalIdTest extends TestCase
{
    /**
     * @var \Nuwave\Lighthouse\Execution\Utils\GlobalId
     */
    private $globalIdResolver;

    protected function setUp(): void
    {
        parent::setUp();

        $this->globalIdResolver = new GlobalId;
    }

    public function testCanHandleGlobalIds(): void
    {
        $globalId = $this->globalIdResolver->encode('User', 'asdf');
        $idParts = $this->globalIdResolver->decode($globalId);

        $this->assertSame(['User', 'asdf'], $idParts);
    }

    public function testCanDecodeJustTheId(): void
    {
        $globalId = $this->globalIdResolver->encode('User', 123);

        $this->assertSame('123', $this->globalIdResolver->decodeID($globalId));
    }

    public function testCanDecodeJustTheType(): void
    {
        $globalId = $this->globalIdResolver->encode('User', 123);

        $this->assertSame('User', $this->globalIdResolver->decodeType($globalId));
    }
}
