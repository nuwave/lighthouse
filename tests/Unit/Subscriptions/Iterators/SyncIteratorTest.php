<?php

namespace Tests\Unit\Subscriptions\Iterators;

use Exception;
use Illuminate\Support\Collection;
use Nuwave\Lighthouse\Subscriptions\Iterators\SyncIterator;
use Tests\TestCase;

class SyncIteratorTest extends TestCase
{
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
            $this->items(),
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
     * @return \Illuminate\Support\Collection<int>
     */
    protected function items(): Collection
    {
        return new Collection([1, 2, 3]);
    }
}
