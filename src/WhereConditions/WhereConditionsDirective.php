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
    """
    columns: [String!]
) on ARGUMENT_DEFINITION | INPUT_FIELD_DEFINITION
SDL;
    }

    /**
     * @param  \Illuminate\Database\Query\Builder|\Illuminate\Database\Eloquent\Builder  $builder
     * @param  mixed[]  $whereConditions
     * @return \Illuminate\Database\Query\Builder|\Illuminate\Database\Eloquent\Builder
     */
    public function handleBuilder($builder, $whereConditions)
    {
        // The value `null` should be allowed but have no effect on the query.
        // Just return the unmodified Builder instance.
        if (is_null($whereConditions)) {
            return $builder;
        }

        return $this->handleWhereConditions($builder, $whereConditions);
    }
}
