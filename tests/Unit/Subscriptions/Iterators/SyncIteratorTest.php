<?php

namespace Tests\Unit\Subscriptions\Iterators;

use Exception;
use Illuminate\Support\Collection;
use Nuwave\Lighthouse\Subscriptions\Iterators\SyncIterator;
use Tests\TestCase;

class SyncIteratorTest extends TestCase
{
    /**
     * @var string
     */
    const EXCEPTION_MESSAGE = 'test_exception';

    /**
     * @var \Nuwave\Lighthouse\Subscriptions\Iterators\SyncIterator
     */
    protected $iterator;

    protected function setUp(): void
    {
        parent::setUp();

        $this->iterator = new SyncIterator;
    }

    public function testCanIterateOverItemsWithCallback(): void
    {
        $items = [];

        $this->iterator->process(
            $this->items(),
            function ($item) use (&$items): void {
                $items[] = $item;
            }
        );

        $this->assertCount(3, $items);
    }

    public function testCanPassExceptionToHandler(): void
    {
        /** @var \Exception|null $exception */
        $exception = null;

        $this->iterator->process(
            $this->items(),
            function (): void {
                throw new Exception(self::EXCEPTION_MESSAGE);
            },
            function (Exception $e) use (&$exception): void {
                $exception = $e;
            }
        );

        $this->assertSame(self::EXCEPTION_MESSAGE, $exception->getMessage());
    }

    /**
     * @return \Illuminate\Support\Collection<int>
     */
    protected function items(): Collection
    {
        return new Collection([1, 2, 3]);
    }
}
