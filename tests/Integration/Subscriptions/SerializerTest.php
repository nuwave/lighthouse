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

        /** @var \Tests\Utils\Models\User|null $userFromContext */
        $userFromContext = $context->user();
        $this->assertNotNull($userFromContext);

        $this->assertSame($user, $userFromContext);

        $retrievedFromDatabase = false;

        User::retrieved(static function () use (&$retrievedFromDatabase) {
            $retrievedFromDatabase = true;
        });

        $unserialized = $serializer->unserialize(
            $serializer->serialize($context)
        );

        $this->assertTrue($retrievedFromDatabase);

        /** @var \Tests\Utils\Models\User|null $unserializedUser */
        $unserializedUser = $unserialized->user();
        $this->assertNotNull($unserializedUser);

        $this->assertSame($user->getKey(), $unserializedUser->getKey());
    }
}
