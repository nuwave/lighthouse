<?php declare(strict_types=1);

namespace Nuwave\Lighthouse\Exceptions;

use GraphQL\Error\ClientAware;

class DirectiveException extends \Exception implements ClientAware
{
    public function isClientSafe(): bool
    {
        return false;
    }
}
