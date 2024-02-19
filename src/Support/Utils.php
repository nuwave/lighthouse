<?php declare(strict_types=1);

namespace Nuwave\Lighthouse\Support;

use Illuminate\Container\Container;
use Illuminate\Support\Str;
use Nuwave\Lighthouse\Exceptions\DefinitionException;

use function Safe\preg_match;
use function Safe\preg_replace;

class Utils
{
    /**
     * Attempt to find a given class in the given namespaces.
     *
     * If the class itself exists, it is simply returned as is.
     * Else, the given namespaces are tried in order.
     *
     * @param  array<string>  $namespacesToTry
     * @param  callable(string $className): bool  $determineMatch
     *
     * @return class-string|null
     */
    public static function namespaceClassname(string $classCandidate, array $namespacesToTry, callable $determineMatch): ?string
    {
        if ($determineMatch($classCandidate)) {
            assert(class_exists($classCandidate));

            return $classCandidate;
        }

        foreach ($namespacesToTry as $namespace) {
            $className = "{$namespace}\\{$classCandidate}";

            if ($determineMatch($className)) {
                assert(class_exists($className));

                return $className;
            }
        }

        return null;
    }

    /**
     * Construct a closure that passes through the arguments.
     *
     * @param  class-string  $className  this class is resolved through the container
     * @param  string  $methodName  the method that gets passed the arguments of the closure
     */
    public static function constructResolver(string $className, string $methodName): \Closure
    {
        if (! method_exists($className, $methodName)) {
            throw new DefinitionException("Method '{$methodName}' does not exist on class '{$className}'.");
        }

        $resolver = Container::getInstance()->make($className);
        assert(is_object($resolver));

        return \Closure::fromCallable([$resolver, $methodName]);
    }

    /**
     * Get the value of a protected member variable of an object.
     *
     * @param  mixed  $object  object with protected member
     * @param  string  $memberName  name of object's protected member
     */
    public static function accessProtected(mixed $object, string $memberName): mixed
    {
        $property = new \ReflectionProperty($object, $memberName);
        $property->setAccessible(true);

        return $property->getValue($object);
    }

    /**
     * Call a protected method of an object.
     *
     * @param  mixed  $object  object with protected method
     * @param  string  $methodName  name of object's protected method
     * @param  array<mixed>  ...$args  zero or more parameters to be passed to the method
     */
    public static function callProtected(mixed $object, string $methodName, array ...$args): mixed
    {
        $property = new \ReflectionMethod($object, $methodName);
        $property->setAccessible(true);

        return $property->invoke($object, ...$args);
    }

    /**
     * Map a value or each value in an array.
     *
     * @param  callable(mixed): mixed  $callback
     * @param  mixed|array<mixed>  $valueOrValues
     *
     * @return mixed|array<mixed>
     */
    public static function mapEach(callable $callback, mixed $valueOrValues): mixed
    {
        if (is_array($valueOrValues)) {
            return array_map($callback, $valueOrValues);
        }

        return $callback($valueOrValues);
    }

    /**
     * Map a value or each value in an array.
     *
     * @param  callable(mixed): mixed  $callback
     * @param  mixed|array<mixed>  $valueOrValues
     *
     * @return mixed|array<mixed>
     */
    public static function mapEachRecursive(callable $callback, mixed $valueOrValues): mixed
    {
        if (is_array($valueOrValues)) {
            return array_map(
                static fn ($value): mixed => static::mapEachRecursive($callback, $value),
                $valueOrValues,
            );
        }

        return $callback($valueOrValues);
    }

    /**
     * Apply a callback to a value or each value in an iterable.
     *
     * @param  callable(mixed): mixed  $callback
     * @param  mixed|iterable<mixed>  $valueOrValues
     */
    public static function applyEach(callable $callback, mixed $valueOrValues): void
    {
        if (is_iterable($valueOrValues)) {
            foreach ($valueOrValues as $value) {
                $callback($value);
            }

            return;
        }

        $callback($valueOrValues);
    }

    /**
     * Determine if a class uses a trait.
     *
     * @param  object|class-string  $class
     */
    public static function classUsesTrait(object|string $class, string $trait): bool
    {
        return in_array(
            $trait,
            class_uses_recursive($class),
        );
    }

    /**
     * Construct a callback that checks if its input is a given class.
     *
     * @param  class-string  $classLike
     *
     * @return \Closure(mixed): bool
     */
    public static function instanceofMatcher(string $classLike): \Closure
    {
        return static fn (mixed $object): bool => $object instanceof $classLike;
    }

    /**
     * Convert the given name to an UPPER_CASE name for an enum value.
     *
     * Ensures compliance with https://spec.graphql.org/draft/#sec-Names.
     *
     * @see \Illuminate\Support\Str::slug().
     */
    public static function toEnumValueName(string $name): string
    {
        // Remove UTF-8 special characters
        $name = Str::ascii($name);
        // Preserve words separated by camelCase
        $name = Str::snake($name);
        // ALL_CAPS
        $name = strtoupper($name);
        // Preserve separator on specific characters like $ for MariaDB and . for MongoDB
        $name = preg_replace('/[$.]/', '_', $name);
        // Remove all characters that are not the separator, letters, numbers, or whitespace
        $name = preg_replace('![^_\pL\pN\s]+!u', '', $name);
        // Replace all separator characters and whitespace by a single separator
        $name = preg_replace('![_\s]+!u', '_', $name);
        // Remove leading or trailing separators
        $name = trim($name, '_');

        // GraphQL names can only start with a letter or underscore
        return preg_match('/^[a-zA-Z_]/', $name)
            ? $name
            : "_{$name}";
    }
}
