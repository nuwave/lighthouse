<?php declare(strict_types=1);

namespace Tests\Integration\Subscriptions;

use Illuminate\Http\Request;
use Nuwave\Lighthouse\Execution\ContextFactory;
use Nuwave\Lighthouse\Execution\ContextSerializer;
use Tests\DBTestCase;
use Tests\EnablesSubscriptionServiceProvider;
use Tests\Utils\Models\User;

final class SerializerTest extends DBTestCase
{
    use EnablesSubscriptionServiceProvider;

    public function testWillSerializeUserModelAndRetrieveItFromTheDatabaseWhenUnserializing(): void
    {
        $user = factory(User::class)->create();

        $contextFactory = new ContextFactory();
        $serializer = new ContextSerializer($contextFactory);

        $request = new Request();
        $request->setUserResolver(static fn () => $user);

        $context = $contextFactory->generate($request);

        $userFromContext = $context->user();
        $this->assertNotNull($userFromContext);
        $this->assertSame($user, $userFromContext);

        $retrievedFromDatabase = false;

        User::retrieved(static function () use (&$retrievedFromDatabase): void {
            $retrievedFromDatabase = true;
        });

        $unserialized = $serializer->unserialize(
            $serializer->serialize($context),
        );

        $this->assertTrue($retrievedFromDatabase);

        $unserializedUser = $unserialized->user();
        $this->assertNotNull($unserializedUser);
        $this->assertSame($user->getKey(), $unserializedUser->getKey());
    }
}
