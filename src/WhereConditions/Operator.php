<?php

namespace Nuwave\Lighthouse\WhereConditions;

/**
 * An Operator handles the database or application specific bits
 * of applying WHERE conditions to a database query builder.
 */
interface Operator
{
    /**
     * Return the GraphQL SDL definition of the operator enum.
     *
     * @return string
     */
    public function enumDefinition(): string;

    /**
     * The default value if no operator is specified.
     *
     * @example "EQ"
     *
     * @return string
     */
    public function default(): string;

    /**
     * Apply the conditions to the query builder.
     *
     * @param  \Illuminate\Database\Query\Builder  $builder
     * @param  array  $whereConditions
     * @param  string  $boolean
     * @return \Illuminate\Database\Query\Builder
     */
    public function applyConditions($builder, array $whereConditions, string $boolean);
}
