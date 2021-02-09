<?php

namespace Nuwave\Lighthouse\Federation;

use GraphQL\Language\AST\DirectiveDefinitionNode;
use GraphQL\Language\AST\NodeList;
use GraphQL\Type\Definition\FieldDefinition;
use GraphQL\Type\Definition\InterfaceType;
use GraphQL\Type\Definition\ObjectType;
use GraphQL\Utils\SchemaPrinter as GraphQLSchemaPrinter;
use Illuminate\Support\Collection;

class SchemaPrinter extends GraphQLSchemaPrinter
{
    protected static function printObject(ObjectType $type, array $options): string
    {
        $interfaces            = $type->getInterfaces();
        $implementedInterfaces = count($interfaces) > 0
            ? ' implements ' . implode(
                ' & ',
                array_map(
                    static function (InterfaceType $interface) : string {
                        return $interface->name;
                    },
                    $interfaces
                )
            )
            : '';

        $schemaDirectives = static::printDirectives($type->astNode->directives);
        $fields = static::printFields($options, $type);
        $description = static::printDescription($options, $type);

        return <<<GRAPHQL
{$description}type {$type->name}{$implementedInterfaces}{$schemaDirectives} {
{$fields}
}
GRAPHQL;
    }

    /**
     * @param  NodeList<\GraphQL\Language\AST\DirectiveDefinitionNode>  $directives
     */
    protected static function printDirectives(NodeList $directives): string
    {
        // TODO maybe filter directives here?

        return count($directives) > 0
            ? (' ' . (new Collection($directives))
                    ->map(static function (DirectiveDefinitionNode $directive): string {
                        return '@' . $directive->name->value . static::printArgs([], $directive->arguments);
                    })
                    ->implode(' '))
            : '';
    }

    /**
     * @param  \GraphQL\Type\Definition\ObjectType|\GraphQL\Type\Definition\InterfaceType  $type
     */
    protected static function printFields(array $options, $type): string
    {
        $firstInBlock = true;

        return implode(
            "\n",
            array_map(
                static function (FieldDefinition $f, int $i) use (&$firstInBlock, $options): string {
                    $description = static::printDescription($options, $f, '  ', $firstInBlock)
                        . '  '
                        . $f->name
                        . static::printArgs($options, $f->args, '  ')
                        . ': '
                        . $f->getType()->name
                        . static::printDirectives($f->astNode->directives)
                        . static::printDeprecated($f);

                    $firstInBlock = false;

                    return $description;
                },
                $type->getFields()
            )
        );
    }

    protected static function printInterface(InterfaceType $type, array $options): string
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
        $directives = static::printDirectives($type->astNode->directives);
        $fields = static::printFields($options, $type);
        return <<<GRAPHQL
{$description}interface {$type->name}{$implementedInterfaces}{$directives} {
{$fields}
}
GRAPHQL;
    }
}
