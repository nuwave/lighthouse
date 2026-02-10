<?php declare(strict_types=1);

namespace Nuwave\Lighthouse\Exceptions;

use GraphQL\Error\ClientAware;
use GraphQL\Error\SyntaxError;
use GraphQL\Language\Source;

class SchemaSyntaxErrorException extends \Exception implements ClientAware
{
    public function __construct(SyntaxError $error)
    {
        $message = $error->getMessage();

        $source = $error->getSource();
        $positions = $error->getPositions();
        if ($source instanceof Source && $positions !== []) {
            $position = $positions[0];

            $from = max(0, $position - 50);
            $surroundingCode = substr($source->body, $from, 100);
            $message .= ", near: {$surroundingCode}";
        }

        parent::__construct($message);
    }

    public function isClientSafe(): bool
    {
        return false;
    }
}
