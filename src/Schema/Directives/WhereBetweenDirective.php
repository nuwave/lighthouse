<?php

namespace Nuwave\Lighthouse\Schema\Directives;

use Nuwave\Lighthouse\Support\Contracts\ArgFilterDirective;

/**
 * @deprecated in favour of
 * @see \Nuwave\Lighthouse\Between\WhereBetweenDirective
 */
class WhereBetweenDirective implements ArgFilterDirective
{
    /**
     * Name of the directive.
     *
     * @return string
     */
    public function name(): string
    {
        return 'whereBetween';
    }

    /**
     * @param  \Illuminate\Database\Query\Builder|\Illuminate\Database\Eloquent\Builder  $builder
     * @param  string  $columnName
     * @param  mixed  $value
     * @return \Illuminate\Database\Query\Builder|\Illuminate\Database\Eloquent\Builder
     */
    public function applyFilter($builder, string $columnName, $value)
    {
        return $builder->whereBetween($columnName, $value);
    }

    /**
     * Does this filter combine the values of multiple input arguments into one query?
     *
     * This is true for filter directives such as "whereBetween" that expects two
     * different input values, given as separate arguments.
     *
     * @return bool
     */
    public function combinesMultipleArguments(): bool
    {
        return true;
    }
}
