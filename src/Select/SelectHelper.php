<?php

namespace Nuwave\Lighthouse\Select;

use GraphQL\Language\AST\DirectiveNode;
use GraphQL\Language\AST\FieldDefinitionNode;
use GraphQL\Language\AST\Node;
use GraphQL\Language\AST\UnionTypeDefinitionNode;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;
use Nuwave\Lighthouse\Schema\AST\ASTBuilder;
use Nuwave\Lighthouse\Schema\AST\ASTHelper;
use Nuwave\Lighthouse\Schema\AST\DocumentAST;
use Nuwave\Lighthouse\Support\AppVersion;
use ReflectionClass;

class SelectHelper
{
    public const DirectivesRequiringLocalKey = ['hasOne', 'hasMany', 'count', 'morphOne', 'morphMany'];

    public const DirectivesRequiringForeignKey = ['belongsTo'];

    public const DirectivesReturn = ['morphTo', 'morphToMany'];

    public const DirectivesIgnore = ['aggregate', 'withCount', 'belongsToMany'];

    /**
     * Given a field definition node, resolve info, and a model name, return the SQL columns that should be selected.
     * Accounts for relationships and to rename and select directives.
     *
     * @param mixed[] $fieldSelection
     *
     * @return string[]
     *
     * @reference https://github.com/nuwave/lighthouse/pull/1626
     */
    public static function getSelectColumns(Node $definitionNode, array $fieldSelection, string $modelName): array
    {
        $returnTypeName = ASTHelper::getUnderlyingTypeName($definitionNode);

        /** @var DocumentAST $documentAST */
        $documentAST = app(ASTBuilder::class)->documentAST();

        if (Str::contains($returnTypeName, ['SimplePaginator', 'Paginator'])) {
            $returnTypeName = str_replace(['SimplePaginator', 'Paginator'], '', $returnTypeName);
        }

        $type = $documentAST->types[$returnTypeName];

        if ($type instanceof UnionTypeDefinitionNode) {
            $type = $documentAST->types[ASTHelper::getUnderlyingTypeName($type->types[0])];
        }

        /** @var iterable<FieldDefinitionNode> $fieldDefinitions */
        $fieldDefinitions = $type->fields;

        /** @var Model $model */
        $model = new $modelName();

        $selectColumns = [];

        foreach ($fieldSelection as $field) {
            $fieldDefinition = ASTHelper::firstByName($fieldDefinitions, $field);

            if ($fieldDefinition) {
                $directivesRequiringKeys = array_merge(self::DirectivesRequiringLocalKey, self::DirectivesRequiringForeignKey, self::DirectivesReturn, self::DirectivesIgnore);

                foreach ($directivesRequiringKeys as $directiveType) {
                    if (ASTHelper::hasDirective($fieldDefinition, $directiveType)) {
                        /** @var DirectiveNode $directive */
                        $directive = ASTHelper::directiveDefinition($fieldDefinition, $directiveType);

                        if (in_array($directiveType, self::DirectivesReturn)) {
                            return [];
                        }

                        if (in_array($directiveType, self::DirectivesRequiringLocalKey)) {
                            $relationName = ASTHelper::directiveArgValue($directive, 'relation', $field);

                            if (method_exists($model, $relationName)) {
                                if (AppVersion::below(5.7)) {
                                    $relation = new ReflectionClass($model->{$relationName}());
                                    $localKey = $relation->getProperty('localKey');
                                    $localKey->setAccessible(true);
                                    array_push($selectColumns, $localKey->getValue($relation));
                                } else {
                                    array_push($selectColumns, $model->{$relationName}()->getLocalKeyName());
                                }
                            }
                        }

                        if (in_array($directiveType, self::DirectivesRequiringForeignKey)) {
                            $relationName = ASTHelper::directiveArgValue($directive, 'relation', $field);

                            if (method_exists($model, $relationName)) {
                                if (AppVersion::below(5.8)) {
                                    array_push($selectColumns, $model->{$relationName}()->getForeignKey());
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

        $selectColumns = array_filter($selectColumns, function ($column) use ($model) {
            return ! $model->hasGetMutator($column) && ! method_exists($model, $column);
        });

        return array_unique($selectColumns);
    }
}
