<?php

namespace Tests\Utils;

use Closure;
use GraphQL\Error\Error;
use Nuwave\Lighthouse\Execution\ErrorHandler;

class ThrowingMiddleware
{
    public function handle(): void
    {
        throw new \Exception('foo');
    }
}
