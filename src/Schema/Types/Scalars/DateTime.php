<?php

namespace Nuwave\Lighthouse\Schema\Types\Scalars;

use Carbon\Carbon;
use GraphQL\Error\Error;
use GraphQL\Utils\Utils;
use GraphQL\Type\Definition\ScalarType;
use GraphQL\Language\AST\StringValueNode;

class DateTime extends ScalarType
{
    public function serialize($value): string
    {
        return $value->toAtomString();
    }

    public function parseValue($value): Carbon
    {
        try {
            $dateTime = Carbon::createFromFormat(Carbon::DEFAULT_TO_STRING_FORMAT, $value);
        } catch (\Exception $e) {
            throw new Error(Utils::printSafeJson($e->getMessage()));
        }

        return $dateTime;
    }

    public function parseLiteral($valueNode, array $variables = null): Carbon
    {
        if (! $valueNode instanceof StringValueNode) {
            throw new Error('Query error: Can only parse strings got: '.$valueNode->kind, [$valueNode]);
        }

        try {
            $dateTime = Carbon::createFromFormat(Carbon::DEFAULT_TO_STRING_FORMAT, $valueNode->value);
        } catch (\Exception $e) {
            throw new Error(Utils::printSafeJson($e->getMessage()));
        }

        return $dateTime;
    }
}
