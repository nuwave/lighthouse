<?php declare(strict_types=1);

namespace Nuwave\Lighthouse\Exceptions;

use GraphQL\Error\ClientAware;

class ClientSafeModelNotFoundException extends \Exception implements ClientAware
{
    public function isClientSafe(): bool
    {
        return true;
    }
}
