<?php

declare(strict_types=1);

namespace Nuwave\Lighthouse\Schema\Types;

use BenSampo\Enum\Enum;
use GraphQL\Type\Definition\EnumType;

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
     * @param  string|\BenSampo\Enum\Enum  $enumClass
     * @return void
     */
    public function __construct(string $enumClass)
    {
        if (! is_subclass_of($enumClass, Enum::class)) {
            throw new \InvalidArgumentException(
                "Must pass an instance of \BenSampo\Enum\Enum, got {$enumClass}."
            );
        }

        $this->enumClass = $enumClass;

        parent::__construct([
            'name' => class_basename($enumClass),
            'values' => array_map(
                function (Enum $enum): array {
                    return [
                        'name' => $enum->key,
                        'value' => $enum->value,
                        'description' => "$enum->value",
                    ];
                },
                $enumClass::getInstances()
            ),
        ]);
    }

    /**
     * Overwrite the native EnumType serialization, as this class does not hold plain values.
     *
     * @param  mixed  $value
     * @return string
     */
    public function serialize($value): string
    {
        if (! $value instanceof Enum) {
            $value = $this->enumClass::getInstance($value);
        }

        return $value->key;
    }
}
