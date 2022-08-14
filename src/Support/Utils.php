<?php

namespace Nuwave\Lighthouse\Support;

use Closure;
use Nuwave\Lighthouse\Exceptions\DefinitionException;
use ReflectionClass;
use ReflectionException;

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
            /** @var class-string $classCandidate */
            return $classCandidate;
        }

        foreach ($namespacesToTry as $namespace) {
            $className = $namespace . '\\' . $classCandidate;

            if ($determineMatch($className)) {
                /** @var class-string $className */
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
     *
     * @throws \Nuwave\Lighthouse\Exceptions\DefinitionException
     */
    public static function constructResolver(string $className, string $methodName): Closure
    {
        if (! method_exists($className, $methodName)) {
            throw new DefinitionException("Method '{$methodName}' does not exist on class '{$className}'");
        }

        return Closure::fromCallable(
            // @phpstan-ignore-next-line this works
            [app($className), $methodName]
        );
    }

    /**
     * Get the value of a protected member variable of an object.
     *
     * Returns a default value in case of error.
     *
     * @param  mixed  $object  object with protected member
     * @param  string  $memberName  name of object's protected member
     * @param  mixed|null  $default  default value to return in case of access error
     *
     * @return mixed value of object's protected member
     */
    public static function accessProtected($object, string $memberName, $default = null)
    {
        try {
            $reflection = new ReflectionClass($object);
            $property = $reflection->getProperty($memberName);
            $property->setAccessible(true);

            return $property->getValue($object);
        } catch (ReflectionException $ex) {
            return $default;
        }
    }

    /**
     * Map a value or each value in an array.
     *
     * @param  mixed|array<mixed>  $valueOrValues
     *
     * @return mixed|array<mixed>
     */
    public static function mapEach(Closure $callback, $valueOrValues)
    {
        if (is_array($valueOrValues)) {
            return array_map($callback, $valueOrValues);
        }

        return $callback($valueOrValues);
    }

    /**
     * Map a value or each value in an array.
     *
     * @param  mixed|array<mixed>  $valueOrValues
     *
     * @return mixed|array<mixed>
     */
    public static function mapEachRecursive(Closure $callback, $valueOrValues)
    {
        if (is_array($valueOrValues)) {
            return array_map(function ($value) use ($callback) {
                return static::mapEachRecursive($callback, $value);
            }, $valueOrValues);
        }

        return $callback($valueOrValues);
    }

    /**
     * Apply a callback to a value or each value in an iterable.
     *
     * @param  mixed|iterable<mixed>  $valueOrValues
     */
    public static function applyEach(Closure $callback, $valueOrValues): void
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
    public static function classUsesTrait($class, string $trait): bool
    {
        return in_array(
            $trait,
            class_uses_recursive($class)
        );
    }

    /**
     * Construct a callback that checks if its input is a given class.
     *
     * @param  class-string  $classLike
     *
     * @return Closure(mixed): bool
     */
    public static function instanceofMatcher(string $classLike): Closure
    {
        return function ($object) use ($classLike): bool {
            return $object instanceof $classLike;
        };
    }
}
