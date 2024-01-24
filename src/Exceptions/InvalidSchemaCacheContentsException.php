<?php declare(strict_types=1);

namespace Nuwave\Lighthouse\Exceptions;

use GraphQL\Utils\Utils;

class InvalidSchemaCacheContentsException extends \Exception
{
    /** @param  mixed  $value the non-array result of `require $path` */
    public function __construct(string $path, mixed $value)
    {
        $notArray = Utils::printSafe($value);
        parent::__construct("Expected the file at {$path} to return an array representation of the schema AST, got: {$notArray}.");
    }
}
