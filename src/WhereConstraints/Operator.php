<?php

namespace Nuwave\Lighthouse\WhereConstraints;

/**
 * An Operator handles the database or application specific bits
 * of applying WHERE constraints to a database query builder.
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
     * Apply the constraints to the query builder.
     *
     * @param  \Illuminate\Database\Query\Builder  $builder
     * @param  array  $whereConstraints
     * @param  string  $boolean
     * @return \Illuminate\Database\Query\Builder
     */
    public function applyConstraints($builder, array $whereConstraints, string $boolean);
}
