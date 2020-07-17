<?php

namespace Nuwave\Lighthouse\Exceptions;

use GraphQL\Error\ClientAware;

/**
 * Exceptions may implement this interface.
 *
 * This enables them to render additional content to the errors
 * entry that is returned to the client when it occurs.
 */
interface RendersErrorsExtensions extends ClientAware
{
    /**
     * Return the content that is put in the "extensions" part
     * of the returned error.
     *
     * @return array<string, mixed>
     */
    public function extensionsContent(): array;
}
