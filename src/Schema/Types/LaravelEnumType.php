<?php

namespace Nuwave\Lighthouse\Schema\Types;

use BenSampo\Enum\Enum;
use GraphQL\Type\Definition\Directive;
use GraphQL\Type\Definition\EnumType;
use InvalidArgumentException;
use ReflectionClassConstant;

/**
 * A convenience wrapper for registering enums programmatically.
 */
class LaravelEnumType extends EnumType
{
    public const DEPRECATED_PHPDOC_TAG = '@deprecated';

    /**
     * @var class-string<\BenSampo\Enum\Enum>
     */
    protected $enumClass;

    /**
     * @var \ReflectionClass<\BenSampo\Enum\Enum>
     */
    protected $reflection;

    /**
     * Create a GraphQL enum from a Laravel enum type.
     *
     * @param  class-string<\BenSampo\Enum\Enum>  $enumClass
     * @param  string|null  $name  The name the enum will have in the schema, defaults to the basename of the given class
     */
    public function __construct(string $enumClass, ?string $name = null)
    {
        if (! class_exists($enumClass)) {
            throw self::classDoesNotExist($enumClass);
        }

        if (! is_subclass_of($enumClass, Enum::class)) {
            throw self::classMustExtendBenSampoEnumEnum($enumClass);
        }

        $this->enumClass = $enumClass;
        $this->reflection = new \ReflectionClass($enumClass);

        parent::__construct([
            'name' => $name ?? class_basename($enumClass),
            'description' => $this->enumDescription($enumClass),
            'values' => array_map(
                /**
                 * @return array<string, mixed> Used to construct a \GraphQL\Type\Definition\EnumValueDefinition
                 */
                function (Enum $enum): array {
                    return [
                        'name' => $enum->key,
                        'value' => $enum,
                        'description' => $enum->description,
                        'deprecationReason' => $this->deprecationReason($enum),
                    ];
                },
                $enumClass::getInstances()
            ),
        ]);
    }

    public static function classDoesNotExist(string $enumClass): InvalidArgumentException
    {
        return new InvalidArgumentException("Class {$enumClass} does not exist.");
    }

    public static function classMustExtendBenSampoEnumEnum(string $enumClass): InvalidArgumentException
    {
        $baseClass = Enum::class;

        return new InvalidArgumentException("Class {$enumClass} must extend {$baseClass}.");
    }

    public static function enumMustHaveKey(Enum $value): InvalidArgumentException
    {
        $class = get_class($value);

        return new InvalidArgumentException("Enum of class {$class} must have key.");
    }

    protected function deprecationReason(Enum $enum): ?string
    {
        $key = $enum->key;
        assert(is_string($key));

        $constant = $this->reflection->getReflectionConstant($key);
        assert($constant instanceof ReflectionClassConstant, 'Enum keys are derived from the constant names');

        $docComment = $constant->getDocComment();
        if (false === $docComment) {
            return null;
        }

        $docComment = substr($docComment, 3); // strip leading /**
        $docComment = substr($docComment, 0, -2); // strip trailing */

        $lines = explode("\n", $docComment);
        foreach ($lines as $line) {
            $parts = explode(self::DEPRECATED_PHPDOC_TAG, $line);

            if (1 === count($parts)) {
                continue;
            }

            $reason = trim($parts[1]);

            return '' === $reason
                ? Directive::DEFAULT_DEPRECATION_REASON
                : $reason;
        }

        return null;
    }

    /**
     * TODO remove check and inline when requiring bensampo/laravel-enum:6.
     *
     * @param  class-string<\BenSampo\Enum\Enum>  $enumClass
     */
    protected function enumDescription(string $enumClass): ?string
    {
        return method_exists($enumClass, 'getClassDescription')
            // @phpstan-ignore-next-line proven to exist by the line above
            ? $enumClass::getClassDescription()
            : null;
    }

    /**
     * Overwrite the native EnumType serialization, as this class does not hold plain values.
     */
    public function serialize($value): string
    {
        if (! $value instanceof Enum) {
            $value = $this->enumClass::fromValue($value);
        }

        $key = $value->key;
        if (! $key) {
            throw static::enumMustHaveKey($value);
        }

        return $key;
    }
}
