<?php

namespace Nuwave\Lighthouse\WhereConditions;

use Illuminate\Support\Str;

class WhereHasConditionsDirective extends WhereConditionsBaseDirective
{
    public static function definition(): string
    {
        return /** @lang GraphQL */ <<<'SDL'
"""
Allows clients to filter a query based on the existence of a related model, using
a dynamically controlled `WHERE` condition that applies to the relationship.
"""
directive @whereHasConditions(
    """
    The Eloquent relationship that the conditions will be applied to.

    This argument can be omitted if the argument name follows the naming
    convention `has{$RELATION}`. For example, if the Eloquent relationship
    is named `posts`, the argument name must be `hasPosts`.
    """
    relation: String

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

        return $builder->whereHas(
            $this->getRelationName(),
            function ($builder) use ($whereConditions): void {
                // This extra nesting is required for the `OR` condition to work correctly.
                $builder->whereNested(
                    function ($builder) use ($whereConditions): void {
                        $this->handleWhereConditions($builder, $whereConditions);
                    }
                );
            }
        );
    }

    /**
     * Get the name of the Eloquent relationship that is used for the query.
     *
     * @return string
     */
    public function getRelationName(): string
    {
        $relationName = $this->directiveArgValue('relation');

        // If the relation name is not set explicitly, we assume the argument
        // name follows a convention and contains the relation name
        if (is_null($relationName)) {
            $relationName = lcfirst(
                Str::after($this->nodeName(), 'has')
            );
        }

        return $relationName;
    }
}
