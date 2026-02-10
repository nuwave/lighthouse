<?php declare(strict_types=1);

namespace Nuwave\Lighthouse\Federation\Types;

use GraphQL\Language\AST\Node;
use GraphQL\Type\Definition\ScalarType;

/**
 * Only necessary for schema validation, not used at runtime.
 */
class FieldSet extends ScalarType
{
    public function serialize(mixed $value): void {}

    public function parseValue(mixed $value): void {}

    public function parseLiteral(Node $valueNode, ?array $variables = null): void {}
}
