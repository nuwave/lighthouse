<?php

namespace Tests\Unit\Support\Utils;

use Tests\TestCase;
use Nuwave\Lighthouse\Support\Utils\GlobalIdUtil;

class GlobalIdUtilTest extends TestCase
{
    /**
     * @test
     */
    public function itCanEncodeGlobalIds()
    {
        $expected = base64_encode('User:1');

        $this->assertEquals(
            $expected,
            GlobalIdUtil::encodeGlobalId('User', 1)
        );
    }

    /**
     * @test
     */
    public function itCanEncodeGlobalIdsWithCustomResolver()
    {
        config(['lighthouse.globalId.encode' => function () {
            return 'foo';
        }]);

        $this->assertEquals(
            'foo',
            GlobalIdUtil::encodeGlobalId('User', 1)
        );
    }

    /**
     * @test
     */
    public function itCanDecodeGlobalIds()
    {
        $globalId = base64_encode('User:1');
        list($type, $id) = GlobalIdUtil::decodeGlobalId($globalId);

        $this->assertEquals($id, 1);
        $this->assertEquals('User', $type);
    }

    /**
     * @test
     */
    public function itCanDecodeRelayIds()
    {
        config(['lighthouse.globalId.decodeId' => function () {
            return ['foo', 'bar'];
        }]);

        $globalId = base64_encode('User:1');
        list($type, $id) = GlobalIdUtil::decodeRelayId($globalId);

        $this->assertEquals('foo', $type);
        $this->assertEquals($id, 'bar');
    }

    /**
     * @test
     */
    public function itCanDecodeRelayTypes()
    {
        $globalId = base64_encode('User:1');

        $this->assertEquals('User', GlobalIdUtil::decodeRelayType($globalId));
    }

    /**
     * @test
     */
    public function itCanDecodeCursors()
    {
        $cursor = base64_encode('arrayconnection:15');

        $this->assertEquals(15, GlobalIdUtil::decodeCursor([
            'after' => $cursor,
            'foo' => 'bar',
        ]));
    }

    /**
     * @test
     */
    public function itCanDecodeCursorId()
    {
        $cursor = base64_encode('arrayconnection:15');

        $this->assertEquals(15, GlobalIdUtil::getCursorId($cursor));
    }
}
