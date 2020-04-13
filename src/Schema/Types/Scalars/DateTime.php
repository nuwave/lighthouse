<?php

namespace Nuwave\Lighthouse\Schema\Types\Scalars;

use Carbon\Carbon;
use Exception;
use GraphQL\Error\Error;
use GraphQL\Error\InvariantViolation;
use GraphQL\Language\AST\StringValueNode;
use GraphQL\Type\Definition\ScalarType;
use GraphQL\Utils\Utils;

class DateTime extends ScalarType
{
    /**
     * Serialize an internal value, ensuring it is a valid datetime string.
     *
     * @param  \Carbon\Carbon|string  $value
     */
    public function serialize($value): string
    {
        if ($value instanceof Carbon) {
            return $value->toDateTimeString();
        }

        return $this
            ->tryParsingDateTime($value, InvariantViolation::class)
            ->toDateTimeString();
    }

    /**
     * Parse a externally provided variable value into a Carbon instance.
     */
    public function parseValue($value): Carbon
    {
        return $this->tryParsingDateTime($value, Error::class);
    }

    /**
     * Parse a literal provided as part of a GraphQL query string into a Carbon instance.
     *
     * @param  \GraphQL\Language\AST\Node  $valueNode
     * @param  mixed[]|null  $variables
     *
     * @throws \GraphQL\Error\Error
     */
    public function parseLiteral($valueNode, ?array $variables = null): Carbon
    {
        if (! $valueNode instanceof StringValueNode) {
            throw new Error(
                'Query error: Can only parse strings got: '.$valueNode->kind,
                [$valueNode]
            );
        }

        return $this->tryParsingDateTime($valueNode->value, Error::class);
    }

    /**
     * Try to parse the given value into a Carbon instance, throw if it does not work.
     *
     * @param  string|\Exception  $exceptionClass
     *
     * @throws \GraphQL\Error\InvariantViolation|\GraphQL\Error\Error
     */
    protected function tryParsingDateTime($value, string $exceptionClass): Carbon
    {
        try {
            return Carbon::createFromFormat(Carbon::DEFAULT_TO_STRING_FORMAT, $value);
        } catch (Exception $e) {
            throw new $exceptionClass(
                Utils::printSafeJson(
                    $e->getMessage()
                )
            );
        }
    }
}
