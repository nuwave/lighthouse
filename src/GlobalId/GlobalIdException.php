<?php declare(strict_types=1);

namespace Nuwave\Lighthouse\GlobalId;

use GraphQL\Error\ClientAware;

class GlobalIdException extends \Exception implements ClientAware
{
    public function isClientSafe(): bool
    {
        return true;
    }
}
