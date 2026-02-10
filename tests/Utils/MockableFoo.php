<?php declare(strict_types=1);

namespace Tests\Utils;

/** Interface for mocking objects with a bar method. */
interface MockableFoo
{
    public function bar(mixed ...$args): mixed;
}
