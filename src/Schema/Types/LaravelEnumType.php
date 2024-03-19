<?php declare(strict_types=1);

namespace Nuwave\Lighthouse\Schema\Types;

use BenSampo\Enum\Enum;
use GraphQL\Type\Definition\Directive;
use GraphQL\Type\Definition\EnumType;

/**
 * A convenience wrapper for registering enums programmatically.
 *
 * @template TValue
 * @template TEnum of \BenSampo\Enum\Enum<TValue>
 */
class LaravelEnumType extends EnumType
{
    public const DEPRECATED_PHPDOC_TAG = '@deprecated';

    /** @var class-string<TEnum> */
    protected string $enumClass;

    /** @var \ReflectionClass<TEnum> */
    protected \ReflectionClass $reflection;

    /**
     * Create a GraphQL enum from a Laravel enum type.
     *
     * @param  class-string<TEnum>  $enumClass
     * @param  string|null  $name  The name the enum will have in the schema, defaults to the basename of the given class
     */
    public function __construct(string $enumClass, ?string $name = null)
    {
        if (! class_exists($enumClass)) {
            throw self::classDoesNotExist($enumClass);
        }

        // @phpstan-ignore-next-line not necessary with full static validation
        if (! is_subclass_of($enumClass, Enum::class)) {
            throw self::classMustExtendBenSampoEnumEnum($enumClass);
        }

        $this->enumClass = $enumClass;
        $this->reflection = new \ReflectionClass($enumClass);

        parent::__construct([
            'name' => $name ?? class_basename($enumClass),
            'description' => $this->enumClassDescription($enumClass),
            'values' => array_map(
                /**
                 * @return array<string, mixed> Used to construct a \GraphQL\Type\Definition\EnumValueDefinition
                 */
                function (Enum $enum): array {
                    $key = $enum->key;
                    if (! $key) {
                        throw static::enumMustHaveKey($enum);
                    }

                    return [
                        'name' => $key,
                        'value' => $enum,
                        'description' => $this->enumValueDescription($enum),
                        'deprecationReason' => $this->deprecationReason($key),
                    ];
                },
                $enumClass::getInstances(),
            ),
        ]);
    }

    public static function classDoesNotExist(string $enumClass): \InvalidArgumentException
    {
        return new \InvalidArgumentException("Class {$enumClass} does not exist.");
    }

    public static function classMustExtendBenSampoEnumEnum(string $enumClass): \InvalidArgumentException
    {
        $baseClass = Enum::class;

        return new \InvalidArgumentException("Class {$enumClass} must extend {$baseClass}.");
    }

    /** @param  \BenSampo\Enum\Enum<mixed>  $value */
    public static function enumMustHaveKey(Enum $value): \InvalidArgumentException
    {
        $class = $value::class;

        return new \InvalidArgumentException("Enum of class {$class} must have key.");
    }

    protected function deprecationReason(string $key): ?string
    {
        $constant = $this->reflection->getReflectionConstant($key);
        assert($constant instanceof \ReflectionClassConstant, 'Enum keys are derived from the constant names');

        $docComment = $constant->getDocComment();
        if ($docComment === false) {
            return null;
        }

        $docComment = substr($docComment, 3); // strip leading /**
        $docComment = substr($docComment, 0, -2); // strip trailing */

        $lines = explode("\n", $docComment);
        foreach ($lines as $line) {
            $parts = explode(self::DEPRECATED_PHPDOC_TAG, $line);

            if (count($parts) === 1) {
                continue;
            }

            $reason = trim($parts[1]);

            return $reason === ''
                ? Directive::DEFAULT_DEPRECATION_REASON
                : $reason;
        }

        return null;
    }

    /**
     * TODO remove check and inline when requiring bensampo/laravel-enum:6.
     *
     * @param  class-string<\BenSampo\Enum\Enum<mixed>>  $enumClass
     */
    protected function enumClassDescription(string $enumClass): ?string
    {
        // @phpstan-ignore-next-line only in some versions
        return method_exists($enumClass, 'getClassDescription')
            ? $enumClass::getClassDescription()
            : null;
    }

    /** @param  TEnum  $enum */
    protected function enumValueDescription(Enum $enum): ?string
    {
        return $enum->description;
    }

    /** Overwrite the native EnumType serialization, as this class does not hold plain values. */
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

    public function parseValue($value)
    {
        if ($value instanceof $this->enumClass) {
            return $value;
        }

        return parent::parseValue($value);
    }
}
