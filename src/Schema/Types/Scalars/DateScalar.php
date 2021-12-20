<?php

namespace Nuwave\Lighthouse\Schema\Types\Scalars;

use Exception;
use GraphQL\Error\Error;
use GraphQL\Error\InvariantViolation;
use GraphQL\Language\AST\StringValueNode;
use GraphQL\Type\Definition\ScalarType;
use GraphQL\Utils\Utils;
use Illuminate\Support\Carbon;

abstract class DateScalar extends ScalarType
{
    /**
     * Serialize an internal value, ensuring it is a valid date string.
     *
     * @param  \Illuminate\Support\Carbon|string  $value
     */
    public function serialize($value): string
    {
        if (! $value instanceof Carbon) {
            $value = $this->tryParsingDate($value, InvariantViolation::class);
        }

        return $this->format($value);
    }

    /**
     * Parse a externally provided variable value into a Carbon instance.
     *
     * @param  string  $value
     */
    public function parseValue($value): Carbon
    {
        return $this->tryParsingDate($value, Error::class);
    }

    /**
     * Parse a literal provided as part of a GraphQL query string into a Carbon instance.
     *
     * @param  \GraphQL\Language\AST\Node  $valueNode
     * @param  array<string, mixed>|null  $variables
     *
     * @throws \GraphQL\Error\Error
     */
    public function parseLiteral($valueNode, ?array $variables = null): Carbon
    {
        if (! $valueNode instanceof StringValueNode) {
            throw new Error(
                "Query error: Can only parse strings, got {$valueNode->kind}",
                $valueNode
            );
        }

        return $this->tryParsingDate($valueNode->value, Error::class);
    }

    /**
     * Try to parse the given value into a Carbon instance, throw if it does not work.
     *
     * @param  mixed  $value  Any value that might be a Date
     * @param  class-string<\Exception>  $exceptionClass
     *
     * @throws \GraphQL\Error\InvariantViolation|\GraphQL\Error\Error
     */
    protected function tryParsingDate($value, string $exceptionClass): Carbon
    {
        try {
            if (
                is_object($value)
                // We want to know if we have exactly a Carbon\Carbon, not a subclass thereof
                // @noRector Rector\CodeQuality\Rector\Identical\GetClassToInstanceOfRector
                && (
                    \Carbon\Carbon::class === get_class($value)
                    || \Carbon\CarbonImmutable::class === get_class($value)
                )
            ) {
                /** @var \Carbon\Carbon|\Carbon\CarbonImmutable $value */

                /**
                 * Given we had a valid \Carbon\Carbon before, this can not fail.
                 *
                 * @var \Illuminate\Support\Carbon $carbon
                 */
                $carbon = Carbon::create(
                    $value->year,
                    $value->month,
                    $value->day,
                    $value->hour,
                    $value->minute,
                    $value->second,
                    $value->timezone
                );

                return $carbon;
            }

            return $this->parse($value);
        } catch (Exception $e) {
            throw new $exceptionClass(
                Utils::printSafeJson($e->getMessage())
            );
        }
    }

    /**
     * Serialize the Carbon instance.
     */
    abstract protected function format(Carbon $carbon): string;

    /**
     * Try turning a client value into a Carbon instance.
     *
     * @param  mixed  $value  a possibly faulty client value
     */
    abstract protected function parse($value): Carbon;
}
