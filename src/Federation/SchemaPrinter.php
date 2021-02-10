<?php

namespace Nuwave\Lighthouse\Federation;

use GraphQL\Language\AST\ArgumentNode;
use GraphQL\Language\AST\DirectiveNode;
use GraphQL\Language\AST\NodeList;
use GraphQL\Language\Printer;
use GraphQL\Type\Definition\FieldDefinition;
use GraphQL\Type\Definition\InterfaceType;
use GraphQL\Type\Definition\ObjectType;
use GraphQL\Utils\SchemaPrinter as GraphQLSchemaPrinter;
use GraphQL\Utils\Utils;
use Illuminate\Support\Collection;

class SchemaPrinter extends GraphQLSchemaPrinter
{
    protected static function printObject(ObjectType $type, array $options): string
    {
        $interfaces = $type->getInterfaces();
        $implementedInterfaces = count($interfaces) > 0
            ? ' implements '.implode(
                ' & ',
                array_map(
                    static function (InterfaceType $interface): string {
                        return $interface->name;
                    },
                    $interfaces
                )
            )
            : '';

        $astNode = $type->astNode;
        $schemaDirectives = $astNode === null
            ? ''
            : static::printDirectives($astNode->directives);

        $fields = static::printFields($options, $type);
        $description = static::printDescription($options, $type);

        return <<<GRAPHQL
{$description}type {$type->name}{$implementedInterfaces}{$schemaDirectives} {
{$fields}
}
GRAPHQL;
    }

    /**
     * @param  \GraphQL\Language\AST\NodeList<\GraphQL\Language\AST\DirectiveNode>  $directives
     */
    protected static function printDirectives(NodeList $directives): string
    {
        // TODO maybe filter directives here?

        return count($directives) > 0
            ? (' '.(new Collection($directives))
                    ->map(static function (DirectiveNode $directive): string {
                        return '@'.$directive->name->value.static::printDirectiveArgs($directive->arguments);
                    })
                    ->implode(' '))
            : '';
    }

    /**
     * @param \GraphQL\Language\AST\NodeList<\GraphQL\Language\AST\ArgumentNode> $args
     */
    protected static function printDirectiveArgs(NodeList $args): string
    {
        if (count($args) === 0) {
            return '';
        }

        return '('
            .implode(
                ', ',
                Utils::map($args, static function (ArgumentNode $arg): string {
                    return $arg->name->value.': '.Printer::doPrint($arg->value);
                })
            )
            .')';
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
                static function (FieldDefinition $f) use (&$firstInBlock, $options): string {
                    $astNode = $f->astNode;
                    $directives = $astNode === null
                        ? ''
                        : static::printDirectives($astNode->directives);

                    $description = static::printDescription($options, $f, '  ', $firstInBlock)
                        .'  '
                        .$f->name
                        .static::printArgs($options, $f->args, '  ')
                        .': '
                        .(string) $f->getType()
                        .$directives
                        .static::printDeprecated($f);

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
            ? ' implements '.implode(
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

        $astNode = $type->astNode;
        $directives = $astNode === null
            ? ''
            : static::printDirectives($astNode->directives);

        $fields = static::printFields($options, $type);

        return <<<GRAPHQL
{$description}interface {$type->name}{$implementedInterfaces}{$directives} {
{$fields}
}
GRAPHQL;
    }
}
