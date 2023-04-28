<?php declare(strict_types=1);

namespace Nuwave\Lighthouse\WhereConditions;

use Illuminate\Contracts\Database\Query\Builder;

/**
 * An Operator handles the database or application specific bits
 * of applying WHERE conditions to a database query builder.
 */
interface Operator
{
    /** Return the GraphQL SDL definition of the operator enum. */
    public function enumDefinition(): string;

    /**
     * The default value if no operator is specified.
     *
     * @example "EQ"
     */
    public function default(): string;

    /**
     * The default value if no has operator is specified.
     *
     * @example "GTE"
     */
    public function defaultHasOperator(): string;

    /**
     * Apply the conditions to the query builder.
     */
    public function applyConditions(Builder $builder, array $whereConditions, string $boolean): Builder;
}
