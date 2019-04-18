<?php

namespace Nuwave\Lighthouse\Support\Contracts;

/**
 * @deprecated in favour of
 * @see \Nuwave\Lighthouse\Support\Contracts\ArgBuilderDirective
 */
interface ArgFilterDirective extends ArgDirective
{
    /**
     * @param  \Illuminate\Database\Query\Builder|\Illuminate\Database\Eloquent\Builder  $builder
     * @param  string  $columnName
     * @param  mixed  $value
     * @return \Illuminate\Database\Query\Builder|\Illuminate\Database\Eloquent\Builder
     */
    public function applyFilter($builder, string $columnName, $value);

    /**
     * Does this filter combine the values of multiple input arguments into one query?
     *
     * This is true for filter directives such as "whereBetween" that expects two
     * different input values, given as separate arguments.
     *
     * @return bool
     */
    public function combinesMultipleArguments(): bool;
}
