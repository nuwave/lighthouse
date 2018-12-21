<?php

namespace Tests\Utils\Scalars;

use GraphQL\Error\Error;
use GraphQL\Utils\Utils;
use GraphQL\Type\Definition\ScalarType;
use GraphQL\Language\AST\StringValueNode;

class Email extends ScalarType
{
    public $name = 'Email';

    public $description = 'Email address.';

    public function serialize($value)
    {
        return $value;
    }

    public function parseValue($value)
    {
        if (! filter_var($value, FILTER_VALIDATE_EMAIL)) {
            throw new Error('Cannot represent following value as email: '.Utils::printSafeJson($value));
        }

        return $value;
    }

    /**
     * @inheritdoc
     */
    public function parseLiteral($valueNode, ?array $variables = null)
    {
        if (! $valueNode instanceof StringValueNode) {
            throw new Error('Query error: Can only parse strings got: '.$valueNode->kind, [$valueNode]);
        }

        if (! filter_var($valueNode->value, FILTER_VALIDATE_EMAIL)) {
            throw new Error('Not a valid email', [$valueNode]);
        }

        return $valueNode->value;
    }
}
