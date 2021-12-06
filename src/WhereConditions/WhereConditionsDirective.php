<?php

namespace Nuwave\Lighthouse\WhereConditions;

class WhereConditionsDirective extends WhereConditionsBaseDirective
{
    public static function definition(): string
    {
        return /** @lang GraphQL */ <<<'GRAPHQL'
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
GRAPHQL;
    }

    /**
     * @param  array<string, mixed>|null  $value
     */
    public function handleBuilder($builder, $value): object
    {
        if (null === $value) {
            return $builder;
        }

        return $this->handleWhereConditions($builder, $value);
    }

    protected function generatedInputSuffix(): string
    {
        return 'WhereConditions';
    }
}
