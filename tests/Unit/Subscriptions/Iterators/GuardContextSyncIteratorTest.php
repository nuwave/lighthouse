<?php

namespace Tests\Unit\Subscriptions\Iterators;

use Exception;
use GraphQL\Type\Definition\ResolveInfo;
use Illuminate\Auth\AuthManager;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Mockery;
use Mockery\MockInterface;
use Nuwave\Lighthouse\Schema\Context;
use Nuwave\Lighthouse\Subscriptions\Iterators\GuardContextSyncIterator;
use Nuwave\Lighthouse\Subscriptions\Subscriber;
use Nuwave\Lighthouse\Subscriptions\SubscriptionGuard;
use Tests\Unit\Subscriptions\SubscriptionTestCase;

class GuardContextSyncIteratorTest extends SubscriptionTestCase
{
    /**
     * @var \Nuwave\Lighthouse\Subscriptions\Iterators\GuardContextSyncIterator
     */
    protected $iterator;

    protected function setUp(): void
    {
        parent::setUp();

        $this->iterator = $this->app->make(GuardContextSyncIterator::class);
    }

    public function testCanIterateOverItemsWithCallback(): void
    {
        $items = [];

        $this->iterator->process(
            $this->subscribers(),
            static function ($item) use (&$items): void {
                $items[] = $item;
            }
        );

        $this->assertCount(3, $items);
    }

    public function testCanPassExceptionToHandler(): void
    {
        $exceptionToThrow = new Exception('test_exception');

        /** @var \Exception|null $exceptionThrown */
        $exceptionThrown = null;

        $this->iterator->process(
            $this->subscribers(),
            static function () use ($exceptionToThrow): void {
                throw $exceptionToThrow;
            },
            static function (Exception $e) use (&$exceptionThrown): void {
                $exceptionThrown = $e;
            }
        );

        $this->assertSame($exceptionToThrow, $exceptionThrown);
    }

    public function testSetsAndResetsGuardContextAfterEachIteration(): void
    {
        // Give each subscriber a user stub and an ID based on the index of the subscriber in the collection
        $subscribers = $this->subscribers()->map(static function (Subscriber $subscriber, int $index) {
            $subscriber->context->user = new GuardContextSyncIteratorAuthenticatableStub($index + 1);

            return $subscriber;
        });

        $guard = Mockery::mock(SubscriptionGuard::class, static function (MockInterface $mock) use ($subscribers) {
            $subscribers->each(static function (Subscriber $subscriber) use ($mock) {
                $mock->shouldReceive('setUser')->with($subscriber->context->user())->once();
                $mock->shouldReceive('user')->andReturn($subscriber->context->user())->once();
                $mock->shouldReceive('reset')->once();
            });
        });

        $authManager = $this->app->make(AuthManager::class);

        $authManager->extend(SubscriptionGuard::GUARD_NAME, static function () use ($guard) {
            return $guard;
        });

        $processedItems = [];
        $authenticatedUser = [];
        $guardBeforeIteration = $authManager->guard();

        $this->iterator->process(
            $subscribers,
            static function (Subscriber $subscriber) use (&$processedItems, &$authenticatedUser, $authManager): void {
                $processedItems[] = $subscriber;
                $authenticatedUser[] = $authManager->user();
            }
        );

        $this->assertCount(3, $processedItems);
        $this->assertSame($subscribers->pluck('context.user')->all(), $authenticatedUser);
        $this->assertSame($guardBeforeIteration, $authManager->guard());
    }

    /**
     * @return \Illuminate\Support\Collection<\Nuwave\Lighthouse\Subscriptions\Subscriber>
     */
    protected function subscribers(): Collection
    {
        return new Collection([
            $this->generateSubscriber(),
            $this->generateSubscriber(),
            $this->generateSubscriber(),
        ]);
    }

    private function generateSubscriber(): Subscriber
    {
        $resolveInfo = $this->createMock(ResolveInfo::class);

        $resolveInfo->operation = (object) [
            'name' => (object) [
                'value' => 'lighthouse',
            ],
        ];

        return new Subscriber([], new Context(new Request), $resolveInfo);
    }
}

class GuardContextSyncIteratorAuthenticatableStub implements Authenticatable
{
    /**
     * @var int
     */
    private $id;

    public function __construct(int $id)
    {
        $this->id = $id;
    }

    public function getAuthIdentifierName()
    {
    }

    public function getAuthIdentifier()
    {
        return $this->id;
    }

    public function getAuthPassword()
    {
    }

    public function getRememberToken()
    {
    }

    public function setRememberToken($value)
    {
    }

    public function getRememberTokenName()
    {
    }
}
