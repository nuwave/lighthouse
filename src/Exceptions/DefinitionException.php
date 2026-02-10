<?php declare(strict_types=1);

namespace Nuwave\Lighthouse\Exceptions;

use GraphQL\Error\ClientAware;

/**
 * Thrown when the schema definition or related code is wrong.
 *
 * This signals a developer error, so we do not show this exception to the user.
 */
class DefinitionException extends \Exception implements ClientAware
{
    public function isClientSafe(): bool
    {
        return false;
    }
}
