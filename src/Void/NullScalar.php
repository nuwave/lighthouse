<?php

namespace Nuwave\Lighthouse\Void;

use GraphQL\Error\Error;
use GraphQL\Error\InvariantViolation;
use GraphQL\Language\AST\Node;
use GraphQL\Language\AST\NullValueNode;
use GraphQL\Type\Definition\ScalarType;
use GraphQL\Utils\Utils;

class NullScalar extends ScalarType
{
    public function serialize($value)
    {
        if ($value !== null) {
            throw new InvariantViolation(static::notNullMessage($value));
        }

        return null;
    }

    public function parseValue($value)
    {
        if ($value !== null) {
            throw new Error(static::notNullMessage($value));
        }

        return null;
    }

    public function parseLiteral(Node $valueNode, ?array $variables = null)
    {
        if (! $valueNode instanceof NullValueNode) {
            throw new Error(static::notNullMessage($valueNode));
        }

        return null;
    }

    /**
     * @param mixed $value any non-null value
     */
    public static function notNullMessage($value): string
    {
        $notNull = Utils::printSafe($value);

        return "Expected null, got: {$notNull}";
    }
}
