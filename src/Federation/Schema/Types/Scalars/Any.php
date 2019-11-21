<?php

namespace Nuwave\Lighthouse\Federation\Schema\Types\Scalars;

use Carbon\Carbon;
use Exception;
use GraphQL\Error\Error;
use GraphQL\Error\InvariantViolation;
use GraphQL\Language\AST\Node;
use GraphQL\Language\AST\StringValueNode;
use GraphQL\Type\Definition\ScalarType;
use GraphQL\Utils\Utils;

class Any extends ScalarType
{
    public function serialize($value)
    {
        // TODO: Implement serialize() method.
    }

    public function parseValue($value)
    {
        // TODO: Implement parseValue() method.
    }

    public function parseLiteral($valueNode, ?array $variables = null)
    {
        // TODO: Implement parseLiteral() method.
    }
}
