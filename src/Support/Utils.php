<?php

namespace Nuwave\Lighthouse\Support;

use Closure;
use ReflectionClass;
use ReflectionException;
use GraphQL\Type\Definition\EnumType;
use GraphQL\Type\Definition\ResolveInfo;
use GraphQL\Type\Definition\FieldDefinition;
use Nuwave\Lighthouse\Exceptions\DefinitionException;

class Utils
{
    /**
     * Attempt to find a given class in the given namespaces.
     *
     * If the class itself exists, it is simply returned as is.
     * Else, the given namespaces are tried in order.
     *
     * @param  string  $classCandidate
     * @param  array  $namespacesToTry
     * @param  callable  $determineMatch
     * @return string|null
     */
    public static function namespaceClassname(string $classCandidate, array $namespacesToTry, callable $determineMatch): ?string
    {
        if ($determineMatch($classCandidate)) {
            return $classCandidate;
        }

        // Stop if the class is found or we are out of namespaces to try
        while (! empty($namespacesToTry)) {
            // Pop off the first namespace and try it
            $className = array_shift($namespacesToTry).'\\'.$classCandidate;

            if ($determineMatch($className)) {
                return $className;
            }
        }

        return null;
    }

    /**
     * Construct a closure that passes through the arguments.
     *
     * @param  string  $className This class is resolved through the container.
     * @param  string  $methodName The method that gets passed the arguments of the closure.
     * @return \Closure
     *
     * @throws \Nuwave\Lighthouse\Exceptions\DefinitionException
     */
    public static function constructResolver(string $className, string $methodName): Closure
    {
        if (! method_exists($className, $methodName)) {
            throw new DefinitionException("Method '{$methodName}' does not exist on class '{$className}'");
        }

        return Closure::fromCallable([app($className), $methodName]);
    }

    /**
     * Get the value of a protected member variable of an object.
     *
     * Returns a default value in case of error.
     *
     * @param  mixed  $object  Object with protected member.
     * @param  string  $memberName  Name of object's protected member.
     * @param  mixed|null  $default  Default value to return in case of access error.
     * @return mixed  Value of object's protected member.
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
     * Apply withTrashed, onlyTrashed or withoutTrashed to given $query if needed.
     * Resolve info is used to get list if argument definitions of current field.
     * If there is any argument of enum type Trash, then modifications are applied.
     *
     * @param \GraphQL\Type\Definition\ResolveInfo $resolveInfo
     * @param array $args
     * @param \Illuminate\Database\Eloquent\Builder | \Laravel\Scout\Builder $query
     *
     * @return void
     */
    public static function applyTrashedModificationIfNeeded(ResolveInfo $resolveInfo, array $args, $query): void
    {
        // skip execution, if model doesn't support soft delete
        if (! in_array('Illuminate\Database\Eloquent\SoftDeletes', class_uses($query->getModel()))) {
            return;
        }

        $trashedArgumentName = null;

        // get field definition
        $fieldDefinition = $resolveInfo->parentType->getField($resolveInfo->fieldName);
        if (! $fieldDefinition instanceof FieldDefinition) {
            return;
        }

        // search for trashed argument name
        foreach ($fieldDefinition->args as $fieldArgument) {
            $fieldArgumentType = $fieldArgument->getType();
            if ($fieldArgumentType instanceof EnumType && $fieldArgumentType->name === 'Trash') {
                $trashedArgumentName = $fieldArgument->name;
            }
        }

        // apply trashed query modification
        if ($trashedArgumentName !== null && array_key_exists($trashedArgumentName, $args)) {
            $trashModificationMethod = "{$args[$trashedArgumentName]}Trashed";
            $query->$trashModificationMethod();
        }
    }
}
