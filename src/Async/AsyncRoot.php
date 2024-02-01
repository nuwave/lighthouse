<?php declare(strict_types=1);

namespace Nuwave\Lighthouse\Async;

/** Used as a marker to signify we are running an async mutation. */
class AsyncRoot
{
    public static function instance(): static
    {
        static $instance;

        return $instance ??= new static();
    }

    protected function __construct() {}
}
