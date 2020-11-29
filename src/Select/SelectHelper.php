<?php

namespace Nuwave\Lighthouse\Select;

use GraphQL\Language\AST\Node;
use Illuminate\Database\Eloquent\Model;
use Nuwave\Lighthouse\Schema\AST\ASTBuilder;
use Nuwave\Lighthouse\Schema\AST\ASTHelper;

class SelectHelper
{
    /**
     * Given a field definition node, resolve info, and a model name, return the SQL columns that should be selected.
     * Accounts for relationships and the rename and select directives.
     *
     * @param mixed[] $fieldSelection
     * @return string[]
     */
    public static function getSelectColumns(Node $definitionNode, array $fieldSelection, string $modelName): array
    {
        $returnTypeName = ASTHelper::getUnderlyingTypeName($definitionNode);

        /** @var \Nuwave\Lighthouse\Schema\AST\DocumentAST $documentAST */
        $documentAST = app(ASTBuilder::class)->documentAST();

        $type = $documentAST->types[$returnTypeName];

        /** @var iterable<\GraphQL\Language\AST\FieldDefinitionNode> $fieldDefinitions */
        $fieldDefinitions = $type->fields;

        $model = new $modelName;

        $selectColumns = [];

        foreach ($fieldSelection as $field) {
            $fieldDefinition = ASTHelper::firstByName($fieldDefinitions, $field);

            if ($fieldDefinition) {
                $name = $fieldDefinition->name->value;

                if (ASTHelper::hasDirective($fieldDefinition, 'select')) {
                    // append selected columns in select directive to seletion
                    $directive = ASTHelper::directiveDefinition($fieldDefinition, 'select');

                    if ($directive) {
                        $selectFields = ASTHelper::directiveArgValue($directive, 'columns') ?? [];
                        $selectColumns = array_merge($selectColumns, $selectFields);
                    }
                } elseif (ASTHelper::hasDirective($fieldDefinition, 'rename')) {
                    // append renamed attribute to selection
                    $directive = ASTHelper::directiveDefinition($fieldDefinition, 'rename');

                    if ($directive) {
                        $renamedAttribute = ASTHelper::directiveArgValue($directive, 'attribute');
                        array_push($selectColumns, $renamedAttribute);
                    }
                } elseif (ASTHelper::hasDirective($fieldDefinition, 'count')) {
                    // append relationship local key
                    $directive = ASTHelper::directiveDefinition($fieldDefinition, 'count');

                    if ($directive) {
                        $relationName = ASTHelper::directiveArgValue($directive, 'relation', $name);

                        if ($relationName) {
                            array_push($selectColumns, $model->{$relationName}()->getLocalKeyName());
                        }
                    }
                } elseif (ASTHelper::hasDirective($fieldDefinition, 'hasOne')) {
                    // append relationship local key
                    $directive = ASTHelper::directiveDefinition($fieldDefinition, 'hasOne');

                    if ($directive) {
                        $relationName = ASTHelper::directiveArgValue($directive, 'relation', $name);
                        array_push($selectColumns, $model->{$relationName}()->getLocalKeyName());
                    }
                } elseif (ASTHelper::hasDirective($fieldDefinition, 'hasMany')) {
                    // append relationship local key
                    $directive = ASTHelper::directiveDefinition($fieldDefinition, 'hasMany');

                    if ($directive) {
                        $relationName = ASTHelper::directiveArgValue($directive, 'relation', $name);
                        array_push($selectColumns, $model->{$relationName}()->getLocalKeyName());
                    }
                } elseif (ASTHelper::hasDirective($fieldDefinition, 'belongsTo')) {
                    // append relationship foreign key
                    $directive = ASTHelper::directiveDefinition($fieldDefinition, 'belongsTo');

                    if ($directive) {
                        $relationName = ASTHelper::directiveArgValue($directive, 'relation', $name);
                        array_push($selectColumns, $model->{$relationName}()->getForeignKeyName());
                    }
                } elseif (ASTHelper::hasDirective($fieldDefinition, 'belongsToMany')) {
                    // append relationship foreign key
                    $directive = ASTHelper::directiveDefinition($fieldDefinition, 'belongsToMany');

                    if ($directive) {
                        $relationName = ASTHelper::directiveArgValue($directive, 'relation', $name);
                        array_push($selectColumns, $model->{$relationName}()->getForeignKeyName());
                    }
                } else {
                    // fallback to selecting the field name
                    array_push($selectColumns, $name);
                }
            }
        }

        return array_unique($selectColumns);
    }
}
