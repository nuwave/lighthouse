<?php declare(strict_types=1);

namespace Tests\Utils\Scalars;

use GraphQL\Error\Error;
use GraphQL\Language\AST\NodeKind;
use GraphQL\Language\AST\StringValueNode;
use GraphQL\Type\Definition\ScalarType;
use GraphQL\Utils\Utils;

final class Email extends ScalarType
{
    public string $name = 'Email';

    public ?string $description = 'Email address.';

    public function serialize($value)
    {
        return $value;
    }

    public function parseValue($value): string
    {
        if (! filter_var($value, FILTER_VALIDATE_EMAIL)) {
            $notEmail = Utils::printSafeJson($value);
            throw new Error("Cannot represent the following value as email: {$notEmail}.");
        }

        return $value;
    }

    /** @param  \GraphQL\Language\AST\VariableNode|\GraphQL\Language\AST\NullValueNode|\GraphQL\Language\AST\IntValueNode|\GraphQL\Language\AST\FloatValueNode|\GraphQL\Language\AST\StringValueNode|\GraphQL\Language\AST\BooleanValueNode|\GraphQL\Language\AST\EnumValueNode|\GraphQL\Language\AST\ListValueNode|\GraphQL\Language\AST\ObjectValueNode  $valueNode */
    public function parseLiteral($valueNode, array $variables = null): string
    {
        if (! $valueNode instanceof StringValueNode) {
            $expectedKind = NodeKind::STRING;
            throw new Error("Expected {$expectedKind}, got: {$valueNode->kind}.", $valueNode);
        }

        if (! filter_var($valueNode->value, FILTER_VALIDATE_EMAIL)) {
            throw new Error('Not a valid email.', $valueNode);
        }

        return $valueNode->value;
    }
}
