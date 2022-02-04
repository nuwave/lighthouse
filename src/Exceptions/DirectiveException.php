<?php

namespace Nuwave\Lighthouse\Exceptions;

use Exception;
use GraphQL\Error\ClientAware;

class DirectiveException extends Exception implements ClientAware
{
    public function isClientSafe(): bool
    {
        return false;
    }

    public function getCategory(): string
    {
        return 'schema';
    }
}
