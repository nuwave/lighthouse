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

    """
    Reference a method that applies the client given conditions to the query builder.

    Expected signature: `(
        \Illuminate\Database\Query\Builder|\Illuminate\Database\Eloquent\Builder $builder,
        array<string, mixed> $whereConditions
    ): void`

    Consists of two parts: a class name and a method name, separated by an `@` symbol.
    If you pass only a class name, the method name defaults to `__invoke`.
    """
    handler: String = "\\Nuwave\\Lighthouse\\WhereConditions\\WhereConditionsHandler"
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

        $this->handle($builder, $value);

        return $builder;
    }

    protected function generatedInputSuffix(): string
    {
        return 'WhereConditions';
    }
}
