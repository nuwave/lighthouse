<?php

namespace Tests\Integration\Subscriptions;

use Illuminate\Http\Request;
use Nuwave\Lighthouse\Execution\ContextFactory;
use Nuwave\Lighthouse\Subscriptions\Serializer;
use Nuwave\Lighthouse\Subscriptions\SubscriptionServiceProvider;
use Tests\DBTestCase;
use Tests\Utils\Models\User;

class SerializerTest extends DBTestCase
{
    protected function getPackageProviders($app): array
    {
        return array_merge(
            parent::getPackageProviders($app),
            [SubscriptionServiceProvider::class]
        );
    }

    public function testWillSerializeUserModelAndRetrieveItFromTheDatabaseWhenUnserializing(): void
    {
        $user = factory(User::class)->create();

        $serializer = new Serializer(
            $contextFactory = new ContextFactory
        );

        $request = new Request();
        $request->setUserResolver(static function () use ($user) {
            return $user;
        });

        $context = $contextFactory->generate($request);

        $this->assertSame($user, $context->user());

        $retrievedFromDatabase = false;

        User::retrieved(static function () use (&$retrievedFromDatabase) {
            $retrievedFromDatabase = true;
        });

        $unserialized = $serializer->unserialize(
            $serializer->serialize($context)
        );

        $this->assertTrue($retrievedFromDatabase);
        $this->assertInstanceOf(User::class, $unserialized->user());
        $this->assertSame($user->getKey(), $unserialized->user()->getKey());
    }

    /**
     * TODO remove this fallback test in v5.
     */
    public function testSerializerIsBackwardsCompatibleWithSerializedUser(): void
    {
        $user = factory(User::class)->create();

        $serializer = new Serializer(
            $contextFactory = new ContextFactory
        );

        $request = new Request();
        $request->setUserResolver(static function () use ($user) {
            return $user;
        });

        $context = $contextFactory->generate($request);

        $this->assertSame($user, $context->user());

        $retrievedFromDatabase = false;

        User::retrieved(static function () use (&$retrievedFromDatabase) {
            $retrievedFromDatabase = true;
        });

        $serialized = serialize([
            'request' => [
                'query' => [],
                'request' => [],
                'attributes' => [],
                'cookies' => [],
                'files' => [],
                'server' => [],
                'content' => '',
            ],
            'user' => serialize($context->user()),
        ]);

        $unserialized = $serializer->unserialize($serialized);

        $this->assertFalse($retrievedFromDatabase);
        $this->assertInstanceOf(User::class, $unserialized->user());
        $this->assertSame($user->getKey(), $unserialized->user()->getKey());
    }
}
