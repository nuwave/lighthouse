<?php

namespace Nuwave\Lighthouse\Schema\Types\Scalars;

use Carbon\Carbon;
use GraphQL\Error\Error;
use GraphQL\Language\AST\StringValueNode;
use GraphQL\Type\Definition\ScalarType;
use GraphQL\Utils\Utils;

class DateTime extends ScalarType
{
    public $name = 'DateTime';

    public $description = 'A date string with format Y-m-d H:i:s. Example: "2018-01-01 13:00:00"';

    public function serialize($value)
    {
        return $value->toAtomString();
    }

    public function parseValue($value)
    {
        try {
            $dateTime = Carbon::createFromFormat('Y-m-d H:i:s', $value);
        } catch (\Exception $e) {
            throw new Error(Utils::printSafeJson($e->getMessage()));
        }

        return $dateTime;
    }

    public function parseLiteral($valueNode, array $variables = null)
    {
        if (! $valueNode instanceof StringValueNode) {
            throw new Error('Query error: Can only parse strings got: '.$valueNode->kind, [$valueNode]);
        }

        try {
            $dateTime = Carbon::createFromFormat('Y-m-d H:i:s', $valueNode->value);
        } catch (\Exception $e) {
            throw new Error(Utils::printSafeJson($e->getMessage()));
        }

        return $dateTime;
    }
}
