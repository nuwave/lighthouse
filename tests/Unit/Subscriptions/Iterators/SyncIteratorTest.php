<?php

namespace Tests\Unit\Subscriptions\Iterators;

use Tests\TestCase;
use Illuminate\Support\Collection;
use Nuwave\Lighthouse\Subscriptions\Iterators\SyncIterator;

class SyncIteratorTest extends TestCase
{
    const EXCEPTION_MESSAGE = 'test_exception';

    /** @var SyncIterator */
    protected $iterator;

    /**
     * Set up test environment.
     */
    protected function setUp()
    {
        parent::setUp();

        $this->iterator = new SyncIterator();
    }

    /**
     * @test
     */
    public function itCanIterateOverItemsWithCallback()
    {
        $items = [];

        $this->iterator->process($this->items(), function ($item) use (&$items) {
            $items[] = $item;
        });

        $this->assertCount(3, $items);
    }

    /**
     * @test
     */
    public function itCanPassExceptionToHandler()
    {
        $exception = null;

        $this->iterator->process($this->items(), function ($item) use (&$items) {
            throw new \Exception(self::EXCEPTION_MESSAGE);
        }, function ($e) use (&$exception) {
            $exception = $e;
        });

        $this->assertInstanceOf(\Exception::class, $exception);
        $this->assertSame(self::EXCEPTION_MESSAGE, $exception->getMessage());
    }

    protected function items(): Collection
    {
        return collect([1, 2, 3]);
    }
}
