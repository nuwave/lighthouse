<?php

namespace Nuwave\Lighthouse\Schema\Factories;

use GraphQL\Type\Definition\UnionType;
use GraphQL\Type\Definition\ScalarType;
use GraphQL\Type\Definition\InterfaceType;
use Nuwave\Lighthouse\Schema\TypeRegistry;
use Nuwave\Lighthouse\Schema\Values\NodeValue;
use Nuwave\Lighthouse\Exceptions\DirectiveException;

class TypeFactory
{
    /**
     * Resolve scalar type.
     *
     * @param NodeValue $value
     * @param string    $scalarClass
     *
     * @return ScalarType
     */
    public static function resolveScalarType(NodeValue $value, $scalarClass): ScalarType
    {
        if (! $scalarClass || ! class_exists($scalarClass)) {
            throw new DirectiveException("Unable to find class [$scalarClass] assigned to $name scalar");
        }

        if (! (new \ReflectionClass($scalarClass))->isSubclassOf(ScalarType::class)) {
            throw new DirectiveException(sprintf(
                '%s must be a subclass of %s',
                $scalarClass,
                ScalarType::class
            ));
        }

        return new $scalarClass([
            'name' => $value->getNodeName(),
            'description' => $value->getNode()->description,
        ]);
    }

    /**
     * Resolve interface type.
     *
     * @param NodeValue $value
     * @param \Closure  $fields
     * @param \Closure  $typeResolver
     *
     * @return InterfaceType
     */
    public static function resolveInterfaceType(NodeValue $value, \Closure $fields, \Closure $typeResolver): InterfaceType
    {
        return new InterfaceType([
            'name' => $value->getNodeName(),
            'description' => $value->getNode()->description,
            'fields' => $fields,
            'resolveType' => $typeResolver,
        ]);
    }

    /**
     * Resolve union type.
     *
     * @param NodeValue $value
     * @param \Closure  $typeResolver
     *
     * @return UnionType
     */
    public static function resolveUnionType(NodeValue $value, \Closure $typeResolver): UnionType
    {
        return new UnionType([
            'name' => $value->getNodeName(),
            'description' => $value->getNode()->description,
            'types' => function () use ($value) {
                return collect($value->getNode()->types)
                    ->map(function ($type) {
                        return resolve(TypeRegistry::class)->get($type->name->value);
                    })
                    ->filter()
                    ->toArray();
            },
            'resolveType' => $typeResolver,
        ]);
    }
}
