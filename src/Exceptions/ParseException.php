<?php

namespace Nuwave\Lighthouse\Exceptions;

use Exception;
use GraphQL\Error\ClientAware;
use GraphQL\Error\SyntaxError;
use GraphQL\Language\Source;

class ParseException extends Exception implements ClientAware
{
    public function __construct(SyntaxError $error)
    {
        $message = $error->getMessage();

        $source = $error->getSource();
        $positions = $error->getPositions();
        if ($source instanceof Source && count($positions) > 0) {
            $position = $positions[0];

            $message .= ', near: ' . \Safe\substr($source->body, max(0, $position - 50), 100);
        }

        parent::__construct($message);
    }

    public function isClientSafe(): bool
    {
        return false;
    }

    public function getCategory(): string
    {
        return 'schema';
    }
}
