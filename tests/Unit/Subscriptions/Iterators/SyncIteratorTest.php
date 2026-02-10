<?php declare(strict_types=1);

namespace Tests\Unit\Subscriptions\Iterators;

use Nuwave\Lighthouse\Subscriptions\Iterators\SyncIterator;

final class SyncIteratorTest extends IteratorTestBase
{
    public function testIsWellBehavedIterator(): void
    {
        $iterator = new SyncIterator();

        $this->assertIteratesOverItemsWithCallback($iterator);
        $this->assertPassesExceptionToHandler($iterator);
    }
}
