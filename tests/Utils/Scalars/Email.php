<?php

namespace Tests\Utils\Scalars;

use GraphQL\Error\Error;
use GraphQL\Language\AST\StringValueNode;
use GraphQL\Type\Definition\ScalarType;
use GraphQL\Utils\Utils;

class Email extends ScalarType
{
    /**
     * @var string
     */
    public $name = 'Email';

    /**
     * @var string
     */
    public $description = 'Email address.';

    public function serialize($value)
    {
        return $value;
    }

    public function parseValue($value): string
    {
        if (! filter_var($value, FILTER_VALIDATE_EMAIL)) {
            throw new Error('Cannot represent following value as email: '.Utils::printSafeJson($value));
        }

        return $value;
    }

    /**
     * @param  \GraphQL\Language\AST\VariableNode|\GraphQL\Language\AST\NullValueNode|\GraphQL\Language\AST\IntValueNode|\GraphQL\Language\AST\FloatValueNode|\GraphQL\Language\AST\StringValueNode|\GraphQL\Language\AST\BooleanValueNode|\GraphQL\Language\AST\EnumValueNode|\GraphQL\Language\AST\ListValueNode|\GraphQL\Language\AST\ObjectValueNode  $valueNode
     *
     * @throws \GraphQL\Error\Error
     */
    public function parseLiteral($valueNode, ?array $variables = null): string
    {
        if (! $valueNode instanceof StringValueNode) {
            throw new Error('Query error: Can only parse strings got: '.$valueNode->kind, $valueNode);
        }

        if (! filter_var($valueNode->value, FILTER_VALIDATE_EMAIL)) {
            throw new Error('Not a valid email', $valueNode);
        }

        return $valueNode->value;
    }
}
