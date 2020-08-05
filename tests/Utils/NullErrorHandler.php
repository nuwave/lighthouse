<?php

namespace Tests\Utils;

use Closure;
use GraphQL\Error\Error;
use Nuwave\Lighthouse\Execution\ErrorHandler;

class NullErrorHandler implements ErrorHandler
{
    public function handle(Error $error, Closure $next): ?array
    {
        return null;
    }
}
