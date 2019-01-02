<?php

namespace Nuwave\Lighthouse\Exceptions;

class ParseClientException extends ParseException
{
    /**
     * Returns true when exception message is safe to be displayed to a client.
     *
     * @api
     *
     * @return bool
     */
    public function isClientSafe(): bool
    {
        return true;
    }
}
