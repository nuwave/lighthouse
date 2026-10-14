<?php declare(strict_types=1);

namespace Nuwave\Lighthouse\Federation;

use GraphQL\Language\AST\DirectiveNode;
use GraphQL\Language\Printer;
use GraphQL\Type\Definition\Argument;
use GraphQL\Type\Definition\EnumType;
use GraphQL\Type\Definition\FieldDefinition;
use GraphQL\Type\Definition\InputObjectField;
use GraphQL\Type\Definition\InputObjectType;
use GraphQL\Type\Definition\InterfaceType;
use GraphQL\Type\Definition\ObjectType;
use GraphQL\Type\Definition\ScalarType;
use GraphQL\Type\Definition\UnionType;
use GraphQL\Type\Schema;
use GraphQL\Utils\SchemaPrinter as GraphQLSchemaPrinter;
use Illuminate\Container\Container;
use Nuwave\Lighthouse\Schema\DirectiveLocator;

class SchemaPrinter extends GraphQLSchemaPrinter
{
    protected static function printSchemaDefinition(Schema $schema): string
    {
        $result = '';

        $schemaExtensionDirectives = FederationHelper::schemaExtensionDirectives($schema);
        if ($schemaExtensionDirectives !== []) {
            $result .= 'extend schema' . self::printDirectives($schemaExtensionDirectives);
        }

        $directivesToCompose = FederationHelper::directivesToCompose($schema);

        if ($directivesToCompose !== []) {
            $directiveLocator = Container::getInstance()->make(DirectiveLocator::class);

            $directivesToComposeDefinitions = array_map(
                static fn (string $directive): string => $directiveLocator
                    ->create($directive)
                    ->definition(),
                $directivesToCompose,
            );
            $result .= "\n\n" . implode("\n\n", $directivesToComposeDefinitions);
        }

        return $result;
    }

    /**
     * @param  array<string, mixed>  $options
     * @param  \GraphQL\Type\Definition\ObjectType|\GraphQL\Type\Definition\InterfaceType  $type
     */
    protected static function printFields(array $options, $type): string
    {
        $firstInBlock = true;

        return implode(
            "\n",
            array_map(
                static function (FieldDefinition $f) use (&$firstInBlock, $options): string {
                    $description = static::printDescription($options, $f, '  ', $firstInBlock)
                        . '  '
                        . $f->name
                        . static::printArgs($options, $f->args, '  ')
                        . ': '
                        . $f->getType()
                        . (isset($options['printDirectives'])
                            ? $options['printDirectives']($f)
                            : '')
                        . static::printDeprecated($f);

                    $firstInBlock = false;

                    return $description;
                },
                $type->getFields(),
            ),
        );
    }

    /** @param  array<string, mixed>  $options */
    protected static function printObject(ObjectType $type, array $options): string
    {
        return static::printObjectLike('type', $type, $options);
    }

    /** @param  array<string, mixed>  $options */
    protected static function printInterface(InterfaceType $type, array $options): string
    {
        return static::printObjectLike('interface', $type, $options);
    }

    /** @param  array<string, mixed>  $options */
    protected static function printObjectLike(string $kind, ObjectType|InterfaceType $type, array $options): string
    {
        $interfaces = $type->getInterfaces();
        $implementedInterfaces = $interfaces !== []
            ? ' implements ' . implode(
                ' & ',
                array_map(
                    static fn (InterfaceType $interface): string => $interface->name,
                    $interfaces,
                ),
            )
            : '';

        $description = static::printDescription($options, $type);
        $directives = isset($options['printDirectives'])
            ? $options['printDirectives']($type)
            : '';
        $fields = static::printFields($options, $type);

        return <<<GRAPHQL
{$description}{$kind} {$type->name}{$implementedInterfaces}{$directives} {
{$fields}
}
GRAPHQL;
    }

    /**
     * @param  array<string, mixed>  $options
     * @param  array<int, \GraphQL\Type\Definition\Argument>  $args
     */
    protected static function printArgs(array $options, array $args, string $indentation = ''): string
    {
        if ($args === []) {
            return '';
        }

        if ($options['sortArguments'] ?? false) {
            usort($args, static fn (Argument $left, Argument $right): int => $left->name <=> $right->name);
        }

        $hasDescriptions = false;
        foreach ($args as $arg) {
            if ($arg->description !== null && $arg->description !== '') {
                $hasDescriptions = true;
                break;
            }
        }

        if (! $hasDescriptions) {
            return '(' . implode(', ', array_map(
                static fn (Argument $arg): string => static::printInputValueWithDirectives($arg, $options),
                $args,
            )) . ')';
        }

        $values = [];
        $firstInBlock = true;
        $previousHasDescription = false;
        foreach ($args as $arg) {
            $hasDescription = $arg->description !== null;
            if ($previousHasDescription && ! $hasDescription) {
                $values[] = '';
            }

            $values[] = static::printDescription($options, $arg, '  ' . $indentation, $firstInBlock)
                . '  ' . $indentation . static::printInputValueWithDirectives($arg, $options);
            $firstInBlock = false;
            $previousHasDescription = $hasDescription;
        }

        return "(\n" . implode("\n", $values) . "\n" . $indentation . ')';
    }

    /** @param  array<string, mixed>  $options */
    protected static function printInputValueWithDirectives(Argument|InputObjectField $value, array $options): string
    {
        return parent::printInputValue($value)
            . (isset($options['printDirectives']) ? $options['printDirectives']($value) : '');
    }

    /** @param  array<string, mixed>  $options */
    protected static function printScalar(ScalarType $type, array $options): string
    {
        return parent::printScalar($type, $options)
            . (isset($options['printDirectives']) ? $options['printDirectives']($type) : '');
    }

    /** @param  array<string, mixed>  $options */
    protected static function printUnion(UnionType $type, array $options): string
    {
        $members = $type->getTypes();

        return static::printDescription($options, $type)
            . 'union ' . $type->name
            . (isset($options['printDirectives']) ? $options['printDirectives']($type) : '')
            . ($members === [] ? '' : ' = ' . implode(' | ', $members));
    }

    /** @param  array<string, mixed>  $options */
    protected static function printEnum(EnumType $type, array $options): string
    {
        $values = [];
        $firstInBlock = true;
        $definitions = $type->getValues();
        if ($options['sortEnumValues'] ?? false) {
            usort($definitions, static fn ($left, $right): int => $left->name <=> $right->name);
        }

        foreach ($definitions as $value) {
            if ($value->astNode === null) {
                foreach ($type->astNode->values ?? [] as $valueNode) {
                    if ($valueNode->name->value === $value->name) {
                        $value = clone $value;
                        $value->astNode = $valueNode;
                        break;
                    }
                }
            }

            $values[] = static::printDescription($options, $value, '  ', $firstInBlock)
                . '  ' . $value->name
                . (isset($options['printDirectives']) ? $options['printDirectives']($value) : '')
                . static::printDeprecated($value);
            $firstInBlock = false;
        }

        return static::printDescription($options, $type)
            . 'enum ' . $type->name
            . (isset($options['printDirectives']) ? $options['printDirectives']($type) : '')
            . static::printBlock($values);
    }

    /** @param  array<string, mixed>  $options */
    protected static function printInputObject(InputObjectType $type, array $options): string
    {
        $fields = [];
        $firstInBlock = true;
        $definitions = $type->getFields();
        if ($options['sortInputFields'] ?? false) {
            ksort($definitions);
        }

        foreach ($definitions as $field) {
            $fields[] = static::printDescription($options, $field, '  ', $firstInBlock)
                . '  ' . static::printInputValueWithDirectives($field, $options);
            $firstInBlock = false;
        }

        $isOneOf = method_exists($type, 'isOneOf') && $type->isOneOf(); // @phpstan-ignore function.alreadyNarrowedType (backward compatibility with older webonyx/graphql-php versions)

        return static::printDescription($options, $type)
            . 'input ' . $type->name
            . ($isOneOf ? ' @oneOf' : '')
            . (isset($options['printDirectives']) ? $options['printDirectives']($type) : '')
            . static::printBlock($fields);
    }

    /** @param  array<\GraphQL\Language\AST\DirectiveNode>  $directives */
    public static function printDirectives(array $directives): string
    {
        if ($directives === []) {
            return '';
        }

        return ' ' . implode(
            ' ',
            array_map(
                static fn (DirectiveNode $directive): string => Printer::doPrint($directive),
                $directives,
            ),
        );
    }
}
