<?php

namespace Nuwave\Lighthouse\GlobalId;

use GraphQL\Error\ClientAware;

class GlobalIdException extends \Exception implements ClientAware
{
    public function isClientSafe(): bool
    {
        return true;
    }

    public function getCategory(): string
    {
        return 'global-id';
    }
}
