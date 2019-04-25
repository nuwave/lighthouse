<?php

namespace Nuwave\Lighthouse\Schema\Types\Scalars;

use Exception;
use Carbon\Carbon;
use GraphQL\Error\Error;
use GraphQL\Utils\Utils;
use GraphQL\Error\InvariantViolation;
use GraphQL\Type\Definition\ScalarType;
use GraphQL\Language\AST\StringValueNode;

class Date extends ScalarType
{
    /**
     * Serialize an internal value, ensuring it is a valid date string.
     *
     * @param  \Carbon\Carbon|string  $value
     * @return string
     */
    public function serialize($value): string
    {
        if ($value instanceof Carbon) {
            return $value->toDateString();
        }

        return $this->tryParsingDate($value, InvariantViolation::class)
            ->toDateString();
    }

    /**
     * Parse a externally provided variable value into a Carbon instance.
     *
     * @param  string  $value
     * @return \Carbon\Carbon
     */
    public function parseValue($value): Carbon
    {
        return $this->tryParsingDate($value, Error::class);
    }

    /**
     * Parse a literal provided as part of a GraphQL query string into a Carbon instance.
     *
     * @param  \GraphQL\Language\AST\Node  $valueNode
     * @param  mixed[]|null  $variables
     * @return \Carbon\Carbon
     *
     * @throws \GraphQL\Error\Error
     */
    public function parseLiteral($valueNode, ?array $variables = null): Carbon
    {
        if (! $valueNode instanceof StringValueNode) {
            throw new Error(
                "Query error: Can only parse strings, got {$valueNode->kind}",
                [$valueNode]
            );
        }

        return $this->tryParsingDate($valueNode->value, Error::class);
    }

    /**
     * Try to parse the given value into a Carbon instance, throw if it does not work.
     *
     * @param  string  $value
     * @param  string  $exceptionClass
     * @return \Carbon\Carbon
     *
     * @throws \GraphQL\Error\InvariantViolation|\GraphQL\Error\Error
     */
    protected function tryParsingDate($value, string $exceptionClass): Carbon
    {
        try {
            return Carbon::createFromFormat('Y-m-d', $value)->startOfDay();
        } catch (Exception $e) {
            throw new $exceptionClass(
                Utils::printSafeJson($e->getMessage())
            );
        }
    }
}
