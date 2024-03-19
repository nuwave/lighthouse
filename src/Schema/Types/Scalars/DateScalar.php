<?php declare(strict_types=1);

namespace Nuwave\Lighthouse\Schema\Types\Scalars;

use Carbon\Carbon as CarbonCarbon;
use Carbon\CarbonImmutable;
use GraphQL\Error\Error;
use GraphQL\Error\InvariantViolation;
use GraphQL\Language\AST\Node;
use GraphQL\Language\AST\StringValueNode;
use GraphQL\Type\Definition\ScalarType;
use Illuminate\Support\Carbon as IlluminateCarbon;

abstract class DateScalar extends ScalarType
{
    /**
     * Serialize an internal value, ensuring it is a valid date string.
     *
     * @param  \Illuminate\Support\Carbon|string  $value
     */
    public function serialize($value): string
    {
        if (! $value instanceof IlluminateCarbon) {
            $value = $this->tryParsingDate($value, InvariantViolation::class);
        }

        return $this->format($value);
    }

    /** Parse an externally provided variable value into a Carbon instance. */
    public function parseValue(mixed $value): IlluminateCarbon
    {
        return $this->tryParsingDate($value, Error::class);
    }

    /**
     * Parse a literal provided as part of a GraphQL query string into a Carbon instance.
     *
     * @param  array<string, mixed>|null  $variables
     */
    public function parseLiteral(Node $valueNode, ?array $variables = null): IlluminateCarbon
    {
        if (! $valueNode instanceof StringValueNode) {
            throw new Error("Query error: Can only parse strings, got {$valueNode->kind}.", $valueNode);
        }

        return $this->tryParsingDate($valueNode->value, Error::class);
    }

    /**
     * Try to parse the given value into a Carbon instance, throw if it does not work.
     *
     * @param  mixed  $value  Any value that might be a Date
     * @param  class-string<\Exception>  $exceptionClass
     */
    protected function tryParsingDate(mixed $value, string $exceptionClass): IlluminateCarbon
    {
        try {
            if (is_object($value)) {
                if ($value::class === IlluminateCarbon::class) {
                    return $value;
                }

                // We want to know if we have exactly a Carbon\Carbon, not a subclass thereof
                if ($value::class === CarbonCarbon::class
                    || $value::class === CarbonImmutable::class
                ) {
                    $carbon = IlluminateCarbon::create(
                        $value->year,
                        $value->month,
                        $value->day,
                        $value->hour,
                        $value->minute,
                        $value->second,
                        $value->timezone,
                    );
                    assert($carbon instanceof IlluminateCarbon, 'Given we had a valid Carbon instance before, this can not fail.');

                    return $carbon;
                }
            }

            return $this->parse($value);
        } catch (\Exception $exception) {
            throw new $exceptionClass($exception->getMessage());
        }
    }

    /** Serialize the Carbon instance. */
    abstract protected function format(IlluminateCarbon $carbon): string;

    /**
     * Try turning a client value into a Carbon instance.
     *
     * @param  mixed  $value  a possibly faulty client value
     */
    abstract protected function parse(mixed $value): IlluminateCarbon;
}
