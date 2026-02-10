<?php declare(strict_types=1);

namespace Tests\Utils;

use GraphQL\Error\Error;
use Nuwave\Lighthouse\Execution\ErrorHandler;

final class NullErrorHandler implements ErrorHandler
{
    public function __invoke(?Error $error, \Closure $next): ?array
    {
        return null;
    }
}
