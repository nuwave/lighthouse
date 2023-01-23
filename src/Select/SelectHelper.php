<?php

namespace Nuwave\Lighthouse\Select;

use GraphQL\Language\AST\DirectiveNode;
use GraphQL\Language\AST\Node;
use GraphQL\Language\AST\NodeList;
use Illuminate\Container\Container;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOneOrMany;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Nuwave\Lighthouse\Pagination\PaginationManipulator;
use Nuwave\Lighthouse\Schema\AST\ASTBuilder;
use Nuwave\Lighthouse\Schema\AST\ASTHelper;
use Nuwave\Lighthouse\Schema\AST\DocumentAST;
use Nuwave\Lighthouse\Support\AppVersion;
use Nuwave\Lighthouse\Support\Utils;

class SelectHelper
{
    public const DIRECTIVES_REQUIRING_LOCAL_KEY = ['hasOne', 'hasMany', 'count', 'morphOne', 'morphMany'];

    public const DIRECTIVES_REQUIRING_FOREIGN_KEY = ['belongsTo'];

    public const DIRECTIVES_REQUIRING_MORPH_KEY = ['morphTo'];

    public const DIRECTIVES = [
        'aggregate',
        'belongsTo',
        'belongsToMany',
        'count',
        'hasOne',
        'hasMany',
        'morphOne',
        'morphMany',
        'morphTo',
        'morphToMany',
        'withCount',
    ];

    /**
     * Given a field definition node, resolve info, and a model name, return the SQL columns that should be selected.
     * Accounts for relationships and to rename and select directives.
     *
     * @param array<int, string> $fieldSelection
     *
     * @return array<int, string>
     *
     * @reference https://github.com/nuwave/lighthouse/pull/1626
     */
    public static function getSelectColumns(Node $definitionNode, array $fieldSelection, string $modelName): array
    {
        if (! ($returnTypeName = PaginationManipulator::getReturnTypeName($definitionNode))) {
            $returnTypeName = ASTHelper::getUnderlyingTypeName($definitionNode);
        }

        $astBuilder = Container::getInstance()->make(ASTBuilder::class);
        assert($astBuilder instanceof ASTBuilder);

        $documentAST = $astBuilder->documentAST();
        assert($documentAST instanceof DocumentAST);

        $type = $documentAST->types[$returnTypeName];

        $fieldDefinitions = $type->fields;
        assert($fieldDefinitions instanceof NodeList);

        $model = new $modelName();
        assert($model instanceof Model);

        $selectColumns = [];

        foreach ($fieldSelection as $field) {
            $foundSelect = false;
            $fieldDefinition = ASTHelper::firstByName($fieldDefinitions, $field);

            if ($fieldDefinition) {
                // the priority of select directive is highest
                if ($directive = ASTHelper::directiveDefinition($fieldDefinition, 'select')) {
                    // append selected columns in select directive to selection
                    $selectFields = ASTHelper::directiveArgValue($directive, 'columns', []);
                    $selectColumns = array_merge($selectColumns, $selectFields);
                    $foundSelect = true;
                }

                foreach (self::DIRECTIVES as $directiveType) {
                    if ($directive = ASTHelper::directiveDefinition($fieldDefinition, $directiveType)) {
                        assert($directive instanceof DirectiveNode);

                        $relationName = ASTHelper::directiveArgValue($directive, 'relation', $field);
                        if (method_exists($model, $relationName)) {
                            $relation = $model->{$relationName}();
                            if (in_array($directiveType, self::DIRECTIVES_REQUIRING_LOCAL_KEY)) {
                                $selectColumns[] = self::getLocalKey($relation);
                            } elseif (in_array($directiveType, self::DIRECTIVES_REQUIRING_FOREIGN_KEY)) {
                                $selectColumns[] = self::getForeignKey($relation);
                            } elseif (in_array($directiveType, self::DIRECTIVES_REQUIRING_MORPH_KEY)) {
                                $selectColumns[] = self::getForeignKey($relation);
                                $selectColumns[] = $relation->getMorphType();
                            } else {
                                $selectColumns[] = $model->getKeyName();
                            }
                        }

                        continue 2;
                    }
                }

                if ($foundSelect) {
                    continue;
                }
                if ($directive = ASTHelper::directiveDefinition($fieldDefinition, 'rename')) {
                    // append renamed attribute to selection
                    $renamedAttribute = ASTHelper::directiveArgValue($directive, 'attribute');
                    $selectColumns[] = $renamedAttribute;
                } elseif (($directive = ASTHelper::directiveDefinition($fieldDefinition, 'method')) || method_exists($model, $field)) {
                    $relation = null !== $directive
                        ? $model->{ASTHelper::directiveArgValue($directive, 'name')}()
                        : $model->{$field}();
                    if ($relation instanceof MorphTo) {
                        $selectColumns[] = self::getForeignKey($relation);
                        $selectColumns[] = $relation->getMorphType();
                    } elseif ($relation instanceof BelongsTo) {
                        $selectColumns[] = self::getForeignKey($relation);
                    } elseif ($relation instanceof HasOneOrMany) {
                        $selectColumns[] = self::getLocalKey($relation);
                    } else {
                        $selectColumns[] = $model->getKeyName();
                    }
                } else {
                    // fallback to selecting the field name
                    $selectColumns[] = $field;
                }
            }
        }

        return array_unique($selectColumns);
    }

    /**
     * Get the local key.
     */
    protected static function getLocalKey(HasOneOrMany $relation): string
    {
        return AppVersion::below(5.7)
            ? Utils::accessProtected($relation, 'localKey')
            : $relation->getLocalKeyName();
    }

    /**
     * Get the foreign key.
     */
    protected static function getForeignKey(BelongsTo $relation): string
    {
        return AppVersion::below(5.8)
            ? $relation->getForeignKey() // @phpstan-ignore-line only be executed on Laravel < 5.8
            : $relation->getForeignKeyName();
    }
}
