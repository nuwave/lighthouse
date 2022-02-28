<?php

namespace Nuwave\Lighthouse\Exceptions;

use Exception;
use GraphQL\Utils\Utils;

class InvalidSchemaCacheContentsException extends Exception
{
    /**
     * @param  mixed  $value the non-array result of `require $path`
     */
    public function __construct(string $path, $value)
    {
        $notArray = Utils::printSafe($value);

        parent::__construct("Expected the file at {$path} to return an array representation of the schema AST, got: {$notArray}.");
    }
}
