<?php

namespace Nuwave\Lighthouse\OrderBy;

use GraphQL\Language\AST\FieldDefinitionNode;
use GraphQL\Language\AST\InputValueDefinitionNode;
use GraphQL\Language\AST\ObjectTypeDefinitionNode;
use GraphQL\Language\Parser;
use Nuwave\Lighthouse\Schema\AST\ASTHelper;
use Nuwave\Lighthouse\Schema\AST\DocumentAST;
use Nuwave\Lighthouse\Schema\Directives\BaseDirective;
use Nuwave\Lighthouse\Support\Contracts\ArgBuilderDirective;
use Nuwave\Lighthouse\Support\Contracts\ArgDirectiveForArray;
use Nuwave\Lighthouse\Support\Contracts\ArgManipulator;
use Nuwave\Lighthouse\Support\Contracts\FieldBuilderDirective;
use Nuwave\Lighthouse\Support\Traits\GeneratesColumnsEnum;

class OrderByDirective extends BaseDirective implements ArgBuilderDirective, ArgDirectiveForArray, ArgManipulator, FieldBuilderDirective
{
    use GeneratesColumnsEnum;

    public static function definition(): string
    {
        return /** @lang GraphQL */ <<<'GRAPHQL'
"""
Sort a result list by one or more given columns.
"""
directive @orderBy(
    """
    Restrict the allowed column names to a well-defined list.
    This improves introspection capabilities and security.
    Mutually exclusive with the `columnsEnum` argument.
    Only used when the directive is added on an argument.
    """
    columns: [String!]

    """
    Use an existing enumeration type to restrict the allowed columns to a predefined list.
    This allowes you to re-use the same enum for multiple fields.
    Mutually exclusive with the `columns` argument.
    Only used when the directive is added on an argument.
    """
    columnsEnum: String

    """
    The database column for which the order by clause will be applied on.
    Only used when the directive is added on a field.
    """
    column: String

    """
    The direction of the order by clause.
    Only used when the directive is added on a field.
    """
    direction: OrderByDirection = ASC
) on ARGUMENT_DEFINITION | FIELD_DEFINITION

"""
Options for the `direction` argument on `@orderBy`.
"""
enum OrderByDirection {
    """
    Sort in ascending order.
    """
    ASC

    """
    Sort in descending order.
    """
    DESC
}
GRAPHQL;
    }

    /**
     * @param  array<array{column: string, order: string}>  $value
     */
    public function handleBuilder($builder, $value): object
    {
        foreach ($value as $orderByClause) {
            $builder->orderBy(
                $orderByClause['column'],
                $orderByClause['order']
            );
        }

        return $builder;
    }

    public function manipulateArgDefinition(
        DocumentAST &$documentAST,
        InputValueDefinitionNode &$argDefinition,
        FieldDefinitionNode &$parentField,
        ObjectTypeDefinitionNode &$parentType
    ): void {
        if ($this->hasAllowedColumns()) {
            $restrictedOrderByName = ASTHelper::qualifiedArgType($argDefinition, $parentField, $parentType).'OrderByClause';
            $argDefinition->type = Parser::typeReference("[$restrictedOrderByName!]");
            $allowedColumnsEnumName = $this->generateColumnsEnum($documentAST, $argDefinition, $parentField, $parentType);

            $documentAST
                ->setTypeDefinition(
                    OrderByServiceProvider::createOrderByClauseInput(
                        $restrictedOrderByName,
                        "Order by clause for the `{$argDefinition->name->value}` argument on the query `{$parentField->name->value}`.",
                        $allowedColumnsEnumName
                    )
                );
        } else {
            $argDefinition->type = Parser::typeReference('['.OrderByServiceProvider::DEFAULT_ORDER_BY_CLAUSE.'!]');
        }
    }

    public function handleFieldBuilder(object $builder): object
    {
        return $builder->orderBy(
            $this->directiveArgValue('column'),
            $this->directiveArgValue('direction', 'ASC')
        );
    }
}
