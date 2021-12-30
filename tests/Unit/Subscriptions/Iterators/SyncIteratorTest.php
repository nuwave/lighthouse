<?php

namespace Tests\Unit\Subscriptions\Iterators;

use Nuwave\Lighthouse\Subscriptions\Iterators\SyncIterator;

class SyncIteratorTest extends IteratorTest
{
    public function testIsWellBehavedIterator(): void
    {
        $iterator = new SyncIterator();

        $this->assertIteratesOverItemsWithCallback($iterator);
        $this->assertPassesExceptionToHandler($iterator);
    }
}
