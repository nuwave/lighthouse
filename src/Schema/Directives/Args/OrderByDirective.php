<?php

namespace Nuwave\Lighthouse\Schema\Directives\Args;

use GraphQL\Language\AST\NonNullTypeNode;
use Nuwave\Lighthouse\Schema\AST\ASTHelper;
use GraphQL\Language\AST\FieldDefinitionNode;
use Nuwave\Lighthouse\Schema\AST\DocumentAST;
use GraphQL\Language\AST\InputValueDefinitionNode;
use GraphQL\Language\AST\ObjectTypeDefinitionNode;
use Nuwave\Lighthouse\Exceptions\DefinitionException;
use Nuwave\Lighthouse\Support\Contracts\ArgManipulator;
use Nuwave\Lighthouse\Support\Contracts\ArgFilterDirective;
use Nuwave\Lighthouse\Support\Contracts\ArgDirectiveForArray;

class OrderByDirective implements ArgFilterDirective, ArgDirectiveForArray, ArgManipulator
{
    /**
     * Name of the directive.
     *
     * @return string
     */
    public function name(): string
    {
        return 'orderBy';
    }

    /**
     * @param  \Illuminate\Database\Query\Builder|\Illuminate\Database\Eloquent\Builder  $builder
     * @param  string  $columnName
     * @param  array[]  $orderByClauses
     * @return \Illuminate\Database\Query\Builder|\Illuminate\Database\Eloquent\Builder
     */
    public function applyFilter($builder, string $columnName, $orderByClauses)
    {
        foreach ($orderByClauses as $orderByClause) {
            $builder->orderBy(
                $orderByClause['field'],
                $orderByClause['order']
            );
        }

        return $builder;
    }

    /**
     * Does this filter combine the values of multiple input arguments into one query?
     *
     * This is true for filter directives such as "whereBetween" that expects two
     * different input values, given as separate arguments.
     *
     * @return bool
     */
    public function combinesMultipleArguments(): bool
    {
        return false;
    }

    /**
     * Validate the input argument definition.
     *
     * @param  \GraphQL\Language\AST\InputValueDefinitionNode  $argDefinition
     * @param  \GraphQL\Language\AST\FieldDefinitionNode  $fieldDefinition
     * @param  \GraphQL\Language\AST\ObjectTypeDefinitionNode  $parentType
     * @param  \Nuwave\Lighthouse\Schema\AST\DocumentAST  $current
     * @return \Nuwave\Lighthouse\Schema\AST\DocumentAST
     */
    public function manipulateSchema(InputValueDefinitionNode $argDefinition, FieldDefinitionNode $fieldDefinition, ObjectTypeDefinitionNode $parentType, DocumentAST $current)
    {
        $expectedOrderByClause = ASTHelper::cloneNode($argDefinition);

        // Users may define this as NonNull if they want
        if ($argDefinition->type instanceof NonNullTypeNode) {
            $expectedOrderByClause = $argDefinition->type;
        }

        if (
            data_get(
                $expectedOrderByClause,
                // Must be a list
                'type'
                // of non-nullable
                .'.type'
                // input objects
                .'.type.name.value'
            ) !== 'OrderByClause'
        ) {
            throw new DefinitionException(
              "Must define the argument type of {$argDefinition->name->value} on field {$fieldDefinition->name->value} as [OrderByClause!]."
            );
        }

        return $current;
    }
}
