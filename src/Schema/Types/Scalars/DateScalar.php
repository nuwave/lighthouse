<?php declare(strict_types=1);

namespace Nuwave\Lighthouse\Schema\Types\Scalars;

use GraphQL\Error\Error;
use GraphQL\Error\InvariantViolation;
use GraphQL\Language\AST\Node;
use GraphQL\Language\AST\StringValueNode;
use GraphQL\Type\Definition\ScalarType;
use Illuminate\Support\Carbon as IlluminateCarbon;

abstract class DateScalar extends ScalarType
{
    /**
     * Serialize an internal value, ensuring it is a valid date object or string.
     *
     * @param  \DateTimeInterface|string  $value
     */
    public function serialize($value): string
    {
        $carbonValue = $this->tryParsingDate($value, InvariantViolation::class);

        return $this->format($carbonValue);
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

        $value = $valueNode->value;

        try {
            return $this->parse($value);
        } catch (\Exception $exception) {
            throw Error::createLocatedError($exception, $valueNode);
        }
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
                if ($value instanceof IlluminateCarbon) {
                    return $value;
                }

                if ($value instanceof \DateTimeInterface) {
                    return IlluminateCarbon::instance($value);
                }
            }

            if (! is_string($value)) {
                throw new $exceptionClass('Query error: Can only parse strings.');
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
     * @param  string  $value a possibly faulty client value
     */
    abstract protected function parse(string $value): IlluminateCarbon;
}
