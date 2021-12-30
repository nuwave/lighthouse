<?php

namespace Nuwave\Lighthouse\Exceptions;

use Exception;

class UnknownCacheVersionException extends Exception
{
    /**
     * @param  mixed  $version  Should be int, but could be something else
     */
    public function __construct($version)
    {
        parent::__construct("Expected lighthouse.cache.version to be 1 or 2, got: {$version}.");
    }
}
