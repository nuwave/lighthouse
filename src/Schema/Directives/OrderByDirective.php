<?php

namespace Nuwave\Lighthouse\Schema\Directives;

use GraphQL\Language\AST\NonNullTypeNode;
use GraphQL\Language\AST\FieldDefinitionNode;
use Nuwave\Lighthouse\Schema\AST\DocumentAST;
use GraphQL\Language\AST\InputValueDefinitionNode;
use GraphQL\Language\AST\ObjectTypeDefinitionNode;
use Nuwave\Lighthouse\Exceptions\DefinitionException;
use Nuwave\Lighthouse\Support\Contracts\ArgManipulator;
use Nuwave\Lighthouse\Support\Contracts\ArgBuilderDirective;
use Nuwave\Lighthouse\Support\Contracts\ArgDirectiveForArray;

class OrderByDirective implements ArgBuilderDirective, ArgDirectiveForArray, ArgManipulator
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
     * Apply an "ORDER BY" clause.
     *
     * @param  \Illuminate\Database\Query\Builder|\Illuminate\Database\Eloquent\Builder  $builder
     * @param  mixed  $value
     * @return \Illuminate\Database\Query\Builder|\Illuminate\Database\Eloquent\Builder
     */
    public function handleBuilder($builder, $value)
    {
        foreach ($value as $orderByClause) {
            $builder->orderBy(
                $orderByClause['field'],
                $orderByClause['order']
            );
        }

        return $builder;
    }

    /**
     * Validate the input argument definition.
     *
     * @param \Nuwave\Lighthouse\Schema\AST\DocumentAST $documentAST
     * @param \GraphQL\Language\AST\InputValueDefinitionNode $argDefinition
     * @param \GraphQL\Language\AST\FieldDefinitionNode $parentField
     * @param \GraphQL\Language\AST\ObjectTypeDefinitionNode $parentType
     * @return void
     *
     * @throws \Nuwave\Lighthouse\Exceptions\DefinitionException
     */
    public function manipulateArgDefinition(
        DocumentAST &$documentAST,
        InputValueDefinitionNode &$argDefinition,
        FieldDefinitionNode &$parentField,
        ObjectTypeDefinitionNode &$parentType
    ): void {
        // Users may define this as NonNull if they want
        // Because we need to validate the structure regardless,
        // we unwrap it by one level if it is
        $expectedOrderByClause = $argDefinition->type instanceof NonNullTypeNode
            ? $argDefinition->type
            : $argDefinition;

        if (
            data_get(
                $expectedOrderByClause,
                // Must be a list
                'type'
                // of non-nullable
                .'.type'
                // input objects
                .'.type.name.value'
                // that are exactly of type
            ) !== 'OrderByClause'
        ) {
            throw new DefinitionException(
              "Must define the argument type of {$argDefinition->name->value} on field {$parentField->name->value} as [OrderByClause!]."
            );
        }
    }
}
