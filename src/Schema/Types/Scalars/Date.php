<?php

namespace Nuwave\Lighthouse\Schema\Types\Scalars;

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
     * @param Carbon|string $value
     *
     * @throws InvariantViolation
     *
     * @return string
     */
    public function serialize($value): string
    {
        if ($value instanceof Carbon){
            return $value->toDateString();
        }

        return $this->tryParsingDate($value, InvariantViolation::class)
            ->toDateString();
    }

    /**
     * Parse a externally provided variable value into a Carbon instance.
     *
     * @param mixed $value
     *
     * @throws Error
     *
     * @return Carbon
     */
    public function parseValue($value): Carbon
    {
        return $this->tryParsingDate($value, Error::class);
    }

    /**
     * Parse a literal provided as part of a GraphQL query string into a Carbon instance.
     *
     * @param \GraphQL\Language\AST\Node $valueNode
     * @param array|null $variables
     *
     * @throws Error
     *
     * @return Carbon
     */
    public function parseLiteral($valueNode, array $variables = null): Carbon
    {
        if (! $valueNode instanceof StringValueNode) {
            throw new Error('Query error: Can only parse strings got: '.$valueNode->kind, [$valueNode]);
        }

        return $this->tryParsingDate($valueNode->value, Error::class);
    }

    /**
     * Try to parse the given value into a string, throw if it does not work.
     *
     * @param mixed $value
     * @param string $exceptionClass
     *
     * @throws
     *
     * @return Carbon
     */
    private function tryParsingDate($value, string $exceptionClass): Carbon
    {
        try {
            return Carbon::createFromFormat('Y-m-d', $value);
        } catch (\Exception $e) {
            throw new $exceptionClass(
                Utils::printSafeJson($e->getMessage())
            );
        }
    }
}
