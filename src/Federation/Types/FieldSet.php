<?php

namespace Nuwave\Lighthouse\Federation\Types;

use GraphQL\Language\AST\Node;
use GraphQL\Type\Definition\ScalarType;

/**
 * Only necessary for schema validation, not used at runtime.
 */
class FieldSet extends ScalarType
{
    public function serialize($value)
    {
    }

    public function parseValue($value)
    {
    }

    public function parseLiteral(Node $valueNode, ?array $variables = null)
    {
    }
}
