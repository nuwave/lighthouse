<?php declare(strict_types=1);

namespace Nuwave\Lighthouse\WhereConditions;

use Illuminate\Database\Eloquent\Builder as EloquentBuilder;
use Illuminate\Database\Query\Builder as QueryBuilder;

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
     *
     * @template TModel of \Illuminate\Database\Eloquent\Model
     *
     * @param  \Illuminate\Database\Query\Builder|\Illuminate\Database\Eloquent\Builder<TModel>  $builder
     * @param  array<string, mixed>  $whereConditions
     *
     * @return \Illuminate\Database\Query\Builder|\Illuminate\Database\Eloquent\Builder<TModel>
     */
    public function applyConditions(QueryBuilder|EloquentBuilder $builder, array $whereConditions, string $boolean): QueryBuilder|EloquentBuilder;
}
