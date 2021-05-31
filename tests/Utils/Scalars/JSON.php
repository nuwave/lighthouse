<?php

namespace Tests\Utils\Scalars;

use GraphQL\Language\AST\BooleanValueNode;
use GraphQL\Language\AST\FloatValueNode;
use GraphQL\Language\AST\IntValueNode;
use GraphQL\Language\AST\ListValueNode;
use GraphQL\Language\AST\ObjectValueNode;
use GraphQL\Language\AST\StringValueNode;
use GraphQL\Type\Definition\ScalarType;

/**
 * Read more about scalars here: http://webonyx.github.io/graphql-php/type-system/scalar-types/
 * Code is from an example here: https://github.com/webonyx/graphql-php/issues/129.
 */
class JSON extends ScalarType
{
    public $description =
        'The `JSON` scalar type represents JSON values as specified by
        [ECMA-404](http://www.ecma-international.org/publications/files/ECMA-ST/ECMA-404.pdf).';

    /**
     * Serializes an internal value to include in a response.
     */
    public function serialize($value)
    {
        // Assuming the internal representation of the value is always correct
        return $value;
    }

    /**
     * Parses an externally provided value (query variable) to use as an input.
     */
    public function parseValue($value)
    {
        return $value;
    }

    /**
     * Parses an externally provided literal value (hardcoded in GraphQL query) to use as an input.
     *
     * E.g.
     * {
     *   user(email: "user@example.com")
     * }
     *
     * @param \GraphQL\Language\AST\Node $valueNode
     * @param mixed[]|null $variables
     */
    public function parseLiteral($valueNode, ?array $variables = null)
    {
        switch ($valueNode) {
            case $valueNode instanceof StringValueNode:
            case $valueNode instanceof BooleanValueNode:
                return $valueNode->value;
            case $valueNode instanceof IntValueNode:
            case $valueNode instanceof FloatValueNode:
                return (float) $valueNode->value;
            case $valueNode instanceof ObjectValueNode:
                $value = [];
                foreach ($valueNode->fields as $field) {
                    $value[$field->name->value] = $this->parseLiteral($field->value);
                }

                return $value;
            case $valueNode instanceof ListValueNode:
                return array_map([$this, 'parseLiteral'], (array) $valueNode->values);
            default:
                return null;
        }
    }
}
