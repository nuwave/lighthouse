<?php

namespace Nuwave\Lighthouse\WhereConditions;

use Exception;
use Illuminate\Database\Eloquent\Builder as EloquentBuilder;
use Illuminate\Support\Str;

class WhereHasConditionsDirective extends WhereConditionsBaseDirective
{
    public static function definition(): string
    {
        return /** @lang GraphQL */ <<<'GRAPHQL'
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
     * @param  array<string, mixed>|null  $value The client given conditions
     */
    public function handleBuilder($builder, $value): object
    {
        if (null === $value) {
            return $builder;
        }

        if (! $builder instanceof EloquentBuilder) {
            throw new Exception('Can not get model from builder of class: '.get_class($builder));
        }
        $model = $builder->getModel();

        $this->handleWhereConditions(
            $builder,
            [
                'HAS' => [
                    'relation' => $this->getRelationName(),
                    'amount' => WhereConditionsServiceProvider::DEFAULT_HAS_AMOUNT,
                    'operator' => '>=',
                    'condition' => $value,
                ],
            ],
            $model
        );

        return $builder;
    }

    /**
     * Get the name of the Eloquent relationship that is used for the query.
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

    protected function generatedInputSuffix(): string
    {
        return 'WhereHasConditions';
    }
}
