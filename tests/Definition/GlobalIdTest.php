<?php

namespace Nuwave\Lighthouse\Tests\Definition;

use Illuminate\Encryption\Encrypter;
use Nuwave\Lighthouse\Tests\TestCase;
use Nuwave\Lighthouse\Support\Traits\GlobalIdTrait;

class GlobalIdTest extends TestCase
{
    /**
     * @test
     */
    public function itCanEncodeId()
    {
        $id = 1;
        $type = 'UserType';
        $field = new GraphQLFieldStub;
        $expected = base64_encode($type.':'.$id);

        $this->assertEquals($expected, $field->encodeGlobalId($type, $id));
    }

    /**
     * @test
     */
    public function itCanDecodeId()
    {
        $id = 1;
        $type = 'UserType';
        $field = new GraphQLFieldStub;
        $encodedId = base64_encode($type.':'.$id);

        list($decodedType, $decodedId) = $field->decodeGlobalId($encodedId);
        $this->assertEquals($id, $decodedId);
        $this->assertEquals($id, $field->decodeRelayId($encodedId));
        $this->assertEquals($type, $field->decodeRelayType($encodedId));
    }

    /**
     * @test
     */
    public function itCanUseConfigToEncodeGlobalId()
    {
        $id = 1;
        $type = 'UserType';
        $encrypter = $this->prophesize(Encrypter::class);
        $this->app->instance('encrypter', $encrypter->reveal());
        $encrypter->encrypt($type.':'.$id)->willReturn('foo');

        $this->app['config']->set('lighthouse.globalId.encode', function ($type, $id) {
            return app('encrypter')->encrypt($type.':'.$id);
        });

        $field = new GraphQLFieldStub;
        $this->assertSame('foo', $field->encodeGlobalId($type, $id));
    }

    /**
     * @test
     */
    public function itCanUseConfigToDecodeRelaylId()
    {
        $globalId = 'foo';
        $encrypter = $this->prophesize(Encrypter::class);
        $this->app->instance('encrypter', $encrypter->reveal());
        $encrypter->decrypt($globalId)->willReturn('bar');

        $this->app['config']->set('lighthouse.globalId.decodeId', function ($globalId) {
            return app('encrypter')->decrypt($globalId);
        });

        $field = new GraphQLFieldStub;
        $this->assertSame('bar', $field->decodeRelayId($globalId));
    }

    /**
     * @test
     */
    public function itCanUseConfigToDecodeRelaylType()
    {
        $globalId = 'foo';
        $encrypter = $this->prophesize(Encrypter::class);
        $this->app->instance('encrypter', $encrypter->reveal());
        $encrypter->decrypt($globalId)->willReturn('baz');

        $this->app['config']->set('lighthouse.globalId.decodeType', function ($globalId) {
            return app('encrypter')->decrypt($globalId);
        });

        $field = new GraphQLFieldStub;
        $this->assertSame('baz', $field->decodeRelayType($globalId));
    }
}

class GraphQLFieldStub
{
    use GlobalIdTrait;
}
