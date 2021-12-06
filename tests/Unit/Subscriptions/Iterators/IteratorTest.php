<?php

namespace Tests\Unit\Subscriptions\Iterators;

use Exception;
use GraphQL\Language\AST\NameNode;
use GraphQL\Language\AST\OperationDefinitionNode;
use GraphQL\Type\Definition\ResolveInfo;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Nuwave\Lighthouse\Schema\Context;
use Nuwave\Lighthouse\Subscriptions\Contracts\SubscriptionIterator;
use Nuwave\Lighthouse\Subscriptions\Subscriber;
use Tests\TestCase;
use Tests\TestsSubscriptions;

abstract class IteratorTest extends TestCase
{
    use TestsSubscriptions;

    public function assertIteratesOverItemsWithCallback(SubscriptionIterator $iterator): void
    {
        $count = 2;
        $subscribers = [];

        $iterator->process(
            $this->subscribers($count),
            static function (Subscriber $subscriber) use (&$subscribers): void {
                $subscribers[] = $subscriber;
            }
        );

        $this->assertCount($count, $subscribers);
    }

    public function assertPassesExceptionToHandler(SubscriptionIterator $iterator): void
    {
        $exceptionToThrow = new Exception('test_exception');

        /** @var \Exception|null $exceptionThrown */
        $exceptionThrown = null;

        $iterator->process(
            $this->subscribers(1),
            static function () use ($exceptionToThrow): void {
                throw $exceptionToThrow;
            },
            static function (Exception $e) use (&$exceptionThrown): void {
                $exceptionThrown = $e;
            }
        );

        $this->assertSame($exceptionToThrow, $exceptionThrown);
    }

    /**
     * @return \Illuminate\Support\Collection<\Nuwave\Lighthouse\Subscriptions\Subscriber>
     */
    protected function subscribers(int $count): Collection
    {
        return Collection::times($count, function (): Subscriber {
            return $this->generateSubscriber();
        });
    }

    public function generateSubscriber(): Subscriber
    {
        $resolveInfo = $this->createMock(ResolveInfo::class);

        $resolveInfo->operation = new OperationDefinitionNode([
            'name' => new NameNode([
                'value' => 'lighthouse',
            ]),
        ]);

        return new Subscriber([], new Context(new Request), $resolveInfo);
    }
}
