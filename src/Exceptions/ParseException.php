<?php

namespace Nuwave\Lighthouse\Exceptions;

use Exception;
use GraphQL\Error\ClientAware;

class ParseException extends Exception implements ClientAware
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
