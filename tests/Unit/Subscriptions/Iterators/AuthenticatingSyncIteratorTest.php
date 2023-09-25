<?php declare(strict_types=1);

namespace Tests\Unit\Subscriptions\Iterators;

use Illuminate\Auth\AuthManager;
use Illuminate\Contracts\Auth\Authenticatable;
use Mockery\MockInterface;
use Nuwave\Lighthouse\Subscriptions\Iterators\AuthenticatingSyncIterator;
use Nuwave\Lighthouse\Subscriptions\Subscriber;
use Nuwave\Lighthouse\Subscriptions\SubscriptionGuard;

final class AuthenticatingSyncIteratorTest extends IteratorTestBase
{
    public function testIsWellBehavedIterator(): void
    {
        $iterator = $this->app->make(AuthenticatingSyncIterator::class);

        $this->assertIteratesOverItemsWithCallback($iterator);
        $this->assertPassesExceptionToHandler($iterator);
    }

    public function testSetsAndResetsGuardContextAfterEachIteration(): void
    {
        $subscriberCount = 3;

        // Give each subscriber a user stub with an ID based on the index of the subscriber in the collection
        $subscribers = $this
            ->subscribers($subscriberCount)
            ->map(static function (Subscriber $subscriber, int $index): Subscriber {
                $user = new AuthenticatingSyncIteratorAuthenticatableStub($index + 1);
                $subscriber->context->setUser($user);

                return $subscriber;
            });

        $guard = \Mockery::mock(SubscriptionGuard::class, static function (MockInterface $mock) use ($subscribers): void {
            $subscribers->each(static function (Subscriber $subscriber) use ($mock): void {
                $user = $subscriber->context->user();

                $mock
                    ->shouldReceive('setUser')
                    ->with($user)
                    ->once();

                $mock
                    ->shouldReceive('user')
                    ->andReturn($user)
                    ->once();

                $mock
                    ->shouldReceive('reset')
                    ->once();
            });
        });

        /** @var \Illuminate\Auth\AuthManager $authManager */
        $authManager = $this->app->make(AuthManager::class);

        $authManager->extend(SubscriptionGuard::GUARD_NAME, static fn (): SubscriptionGuard => $guard);

        $processedItems = [];
        $authenticatedUsers = [];
        $guardBeforeIteration = $authManager->guard();

        $iterator = $this->app->make(AuthenticatingSyncIterator::class);
        $iterator->process(
            $subscribers,
            static function (Subscriber $subscriber) use (&$processedItems, &$authenticatedUsers, $authManager): void {
                $processedItems[] = $subscriber;
                $authenticatedUsers[] = $authManager->user();
            },
        );

        $this->assertCount($subscriberCount, $processedItems);
        $this->assertSame($subscribers->pluck('context.user')->all(), $authenticatedUsers);
        $this->assertSame($guardBeforeIteration, $authManager->guard());
    }
}

final class AuthenticatingSyncIteratorAuthenticatableStub implements Authenticatable
{
    public function __construct(private int $id) {}

    public function getAuthIdentifierName()
    {
        return '';
    }

    public function getAuthIdentifier()
    {
        return $this->id;
    }

    public function getAuthPassword()
    {
        return '';
    }

    public function getRememberToken()
    {
        return '';
    }

    public function setRememberToken($value): void {}

    public function getRememberTokenName()
    {
        return '';
    }
}
