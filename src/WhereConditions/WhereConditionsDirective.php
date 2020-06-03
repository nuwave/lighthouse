<?php

namespace Nuwave\Lighthouse\WhereConditions;

class WhereConditionsDirective extends WhereConditionsBaseDirective
{
    public static function definition(): string
    {
        return /** @lang GraphQL */ <<<'SDL'
"""
Add a dynamically client-controlled WHERE condition to a fields query.
"""
directive @whereConditions(
    """
    Restrict the allowed column names to a well-defined list.
    This improves introspection capabilities and security.
    Mutually exclusive with the `columnsEnum` argument.
    """
    columns: [String!]

    """
    Use an existing enumeration type to restrict the allowed columns to a predefined list.
    This allowes you to re-use the same enum for multiple fields.
    Mutually exclusive with the `columns` argument.
    """
    columnsEnum: String
) on ARGUMENT_DEFINITION
SDL;
    }

    /**
     * @param  \Illuminate\Database\Query\Builder|\Illuminate\Database\Eloquent\Builder  $builder
     * @param  array<string, mixed>|null  $whereConditions
     * @return \Illuminate\Database\Query\Builder|\Illuminate\Database\Eloquent\Builder
     */
    public function handleBuilder($builder, $whereConditions)
    {
        // The value `null` should be allowed but have no effect on the query.
        if (is_null($whereConditions)) {
            return $builder;
        }

        return $this->handleWhereConditions($builder, $whereConditions);
    }
}
