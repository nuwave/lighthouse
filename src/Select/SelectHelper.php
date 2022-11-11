<?php

namespace Nuwave\Lighthouse\Select;

use GraphQL\Language\AST\Node;
use GraphQL\Language\AST\UnionTypeDefinitionNode;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Nuwave\Lighthouse\Schema\AST\ASTBuilder;
use Nuwave\Lighthouse\Schema\AST\ASTHelper;

class SelectHelper
{
    public const DirectivesRequiringLocalKey = ['hasOne', 'hasMany', 'count'];

    public const DirectivesRequiringForeignKey = ['belongsTo', 'belongsToMany', 'morphTo'];

    /**
     * Given a field definition node, resolve info, and a model name, return the SQL columns that should be selected.
     * Accounts for relationships and the rename and select directives.
     *
     * @param mixed[] $fieldSelection
     *
     * @return string[]
     */
    public static function getSelectColumns(Node $definitionNode, array $fieldSelection, string $modelName): array
    {
        DB::disableQueryLog();

        $returnTypeName = ASTHelper::getUnderlyingTypeName($definitionNode);

        /** @var \Nuwave\Lighthouse\Schema\AST\DocumentAST $documentAST */
        $documentAST = app(ASTBuilder::class)->documentAST();

        if (Str::contains($returnTypeName, ['SimplePaginator', 'Paginator'], true)) {
            $returnTypeName = Str::replace(['SimplePaginator', 'Paginator'], '', $returnTypeName);
        }

        // ignore relation closure, e.g. RelationOrderByClause
        foreach (array_keys($documentAST->types) as $type) {
            if (Str::contains($type, ['RelationOrderByClause'], true)) {
                return [];
            }
        }

        $type = $documentAST->types[$returnTypeName];

        if ($type instanceof UnionTypeDefinitionNode) {
            $type = $documentAST->types[ASTHelper::getUnderlyingTypeName($type->types[0])];
        }

        /** @var iterable<\GraphQL\Language\AST\FieldDefinitionNode> $fieldDefinitions */
        $fieldDefinitions = $type->fields;

        $model = new $modelName();

        $selectColumns = [];

        foreach ($fieldSelection as $field) {
            $fieldDefinition = ASTHelper::firstByName($fieldDefinitions, $field);

            if ($fieldDefinition) {
                $directivesRequiringKeys = array_merge(self::DirectivesRequiringLocalKey, self::DirectivesRequiringForeignKey);

                foreach ($directivesRequiringKeys as $directiveType) {
                    if (ASTHelper::hasDirective($fieldDefinition, $directiveType)) {
                        $directive = ASTHelper::directiveDefinition($fieldDefinition, $directiveType);

                        if (in_array($directiveType, self::DirectivesRequiringLocalKey)) {
                            $relationName = ASTHelper::directiveArgValue($directive, 'relation', $field);

                            if (method_exists($model, $relationName)) {
                                array_push($selectColumns, $model->{$relationName}()->getLocalKeyName());
                            }
                        }

                        if (in_array($directiveType, self::DirectivesRequiringForeignKey)) {
                            $relationName = ASTHelper::directiveArgValue($directive, 'relation', $field);

                            if (method_exists($model, $relationName)) {
                                if ($directiveType === 'belongsToMany') {
                                    array_push($selectColumns, $model->{$relationName}()->getForeignPivotKeyName());
                                } else {
                                    array_push($selectColumns, $model->{$relationName}()->getForeignKeyName());
                                }
                            }
                        }

                        continue 2;
                    }
                }

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
                } else {
                    // fallback to selecting the field name
                    array_push($selectColumns, $field);
                }
            }
        }

        // for unit test query log check

        try {
            $allColumns = Schema::getColumnListing($model->getTable());
        } catch (\Exception $e) {
            // connection refuse
            $allColumns = [];
        }

        DB::enableQueryLog();

        return !empty($allColumns)
            ? array_intersect($allColumns, array_unique($selectColumns))
            : array_unique($selectColumns);
    }
}
