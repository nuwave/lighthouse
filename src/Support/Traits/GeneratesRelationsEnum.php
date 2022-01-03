<?php

namespace Nuwave\Lighthouse\Support\Traits;

use GraphQL\Language\AST\EnumTypeDefinitionNode;
use GraphQL\Language\AST\FieldDefinitionNode;
use GraphQL\Language\AST\InputValueDefinitionNode;
use GraphQL\Language\AST\ObjectTypeDefinitionNode;
use GraphQL\Language\Parser;
use Illuminate\Support\Str;
use Nuwave\Lighthouse\Exceptions\DefinitionException;
use Nuwave\Lighthouse\Schema\AST\ASTHelper;
use Nuwave\Lighthouse\Schema\AST\DocumentAST;

/**
 * Directives may want to constrain relations to an enum.
 *
 * @mixin \Nuwave\Lighthouse\Schema\Directives\BaseDirective
 */
trait GeneratesRelationsEnum
{
    /**
     * Check whether the directive constrains allowed relations.
     */
    protected function hasAllowedRelations(): bool
    {
        $hasRelations = null !== $this->directiveArgValue('relations');
        $hasRelationsEnum = null !== $this->directiveArgValue('relationsEnum');

        if ($hasRelations && $hasRelationsEnum) {
            throw new DefinitionException(
                "The @{$this->name()} directive can only have one of the following arguments: `relations`, `relationsEnum`."
            );
        }

        return $hasRelations || $hasRelationsEnum;
    }

    /**
     * Generate the enum type for the list of allowed relations.
     *
     * @return string the name of the used enum
     */
    protected function generateRelationsEnum(
        DocumentAST &$documentAST,
        InputValueDefinitionNode &$argDefinition,
        FieldDefinitionNode &$parentField,
        ObjectTypeDefinitionNode &$parentType
    ): string {
        $relationsEnum = $this->directiveArgValue('relationsEnum');

        if (null !== $relationsEnum) {
            return $relationsEnum;
        }

        $allowedRelationsEnumName = ASTHelper::qualifiedArgType($argDefinition, $parentField, $parentType) . 'Relation';

        $documentAST->setTypeDefinition(
            static::createAllowedRelationsEnum(
                $argDefinition,
                $parentField,
                $parentType,
                $this->directiveArgValue('relations'),
                $allowedRelationsEnumName
            )
        );

        return $allowedRelationsEnumName;
    }

    /**
     * Create the enum that holds the allowed relations.
     *
     * @param  array<mixed, string>  $allowedRelations
     */
    protected function createAllowedRelationsEnum(
        InputValueDefinitionNode &$argDefinition,
        FieldDefinitionNode &$parentField,
        ObjectTypeDefinitionNode &$parentType,
        array $allowedRelations,
        string $allowedRelationsEnumName
    ): EnumTypeDefinitionNode {
        $enumValues = array_map(
            function (string $relationName): string {
                $separatedRelations = str_replace('.', '__', $relationName);
                $key = strtoupper(
                    Str::snake($separatedRelations)
                );

                return "{$key} @enum(value: \"{$relationName}\")";
            },
            $allowedRelations
        );

        $enumValuesString = implode("\n", $enumValues);

        return Parser::enumTypeDefinition(/** @lang GraphQL */ <<<GRAPHQL
"Allowed relation names for {$parentType->name->value}.{$parentField->name->value}.{$argDefinition->name->value}."
enum {$allowedRelationsEnumName} {
    {$enumValuesString}
}
GRAPHQL
        );
    }
}
