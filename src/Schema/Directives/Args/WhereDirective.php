<?php

namespace Nuwave\Lighthouse\Schema\Directives\Args;

use Nuwave\Lighthouse\Schema\Directives\BaseDirective;
use Nuwave\Lighthouse\Support\Contracts\ArgFilterDirective;

class WhereDirective extends BaseDirective implements ArgFilterDirective
{
    /**
     * Name of the directive.
     *
     * @return string
     */
    public function name(): string
    {
        return 'where';
    }

    /**
     * @param  \Illuminate\Database\Query\Builder|\Illuminate\Database\Eloquent\Builder  $builder
     * @param  string  $columnName
     * @param  mixed  $value
     * @return \Illuminate\Database\Query\Builder|\Illuminate\Database\Eloquent\Builder
     */
    public function applyFilter($builder, string $columnName, $value)
    {
        $operator = $this->directiveArgValue('operator', '=');
        $clause = $this->directiveArgValue('clause');

        return $clause
            ? $builder->{$clause}($columnName, $operator, $value)
            : $builder->where($columnName, $operator, $value);
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
        return false;
    }
}
