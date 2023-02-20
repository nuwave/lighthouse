<?php

namespace Nuwave\Lighthouse\Federation;

use GraphQL\Language\AST\DirectiveNode;
use GraphQL\Language\Printer;
use GraphQL\Type\Definition\FieldDefinition;
use GraphQL\Type\Definition\InterfaceType;
use GraphQL\Type\Definition\ObjectType;
use GraphQL\Type\Definition\Type;
use GraphQL\Utils\SchemaPrinter as GraphQLSchemaPrinter;

class SchemaPrinter extends GraphQLSchemaPrinter
{
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
                $type->getFields()
            )
        );
    }

    /**
     * @param  array<string, mixed>  $options
     */
    protected static function printObject(ObjectType $type, array $options): string
    {
        return static::printObjectLike('type', $type, $options);
    }

    /**
     * @param  array<string, mixed>  $options
     */
    protected static function printInterface(InterfaceType $type, array $options): string
    {
        return static::printObjectLike('interface', $type, $options);
    }

    /**
     * @param  \GraphQL\Type\Definition\ObjectType|\GraphQL\Type\Definition\InterfaceType  $type
     * @param  array<string, mixed>  $options
     */
    protected static function printObjectLike(string $kind, Type $type, array $options): string
    {
        $interfaces = $type->getInterfaces();
        $implementedInterfaces = count($interfaces) > 0
            ? ' implements ' . implode(
                ' & ',
                array_map(
                    static function (InterfaceType $interface): string {
                        return $interface->name;
                    },
                    $interfaces
                )
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
     * @param  array<\GraphQL\Language\AST\DirectiveNode>  $directives
     */
    public static function printDirectives(array $directives): string
    {
        if (0 === count($directives)) {
            return '';
        }

        return ' '
            . implode(
                ' ',
                array_map(
                    static function (DirectiveNode $directive): string {
                        return Printer::doPrint($directive);
                    },
                    $directives
                )
            );
    }
}
