<?php

namespace Nuwave\Lighthouse\Schema\Types;

use BenSampo\Enum\Enum;
use GraphQL\Type\Definition\EnumType;
use InvalidArgumentException;

/**
 * A convenience wrapper for registering enums programmatically.
 */
class LaravelEnumType extends EnumType
{
    /**
     * @var string|\BenSampo\Enum\Enum
     */
    protected $enumClass;

    /**
     * Create a GraphQL enum from a Laravel enum type.
     *
     * @param  class-string<\BenSampo\Enum\Enum>  $enumClass
     * @param  string|null  $name  The name the enum will have in the schema, defaults to the basename of the given class
     */
    public function __construct(string $enumClass, ?string $name = null)
    {
        if (! is_subclass_of($enumClass, Enum::class)) {
            throw new InvalidArgumentException(
                "Must pass an instance of \BenSampo\Enum\Enum, got {$enumClass}."
            );
        }

        $this->enumClass = $enumClass;

        parent::__construct([
            'name' => $name ?? class_basename($enumClass),
            'values' => array_map(
                /**
                 * @return array<string, mixed> Used to construct a \GraphQL\Type\Definition\EnumValueDefinition
                 */
                function (Enum $enum): array {
                    return [
                        'name' => $enum->key,
                        'value' => $enum,
                        'description' => $enum->description,
                    ];
                },
                $enumClass::getInstances()
            ),
        ]);
    }

    /**
     * Overwrite the native EnumType serialization, as this class does not hold plain values.
     */
    public function serialize($value): string
    {
        if (! $value instanceof Enum) {
            $value = $this->enumClass::getInstance($value);
        }

        return $value->key;
    }
}
