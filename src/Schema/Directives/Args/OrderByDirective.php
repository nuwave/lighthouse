<?php


namespace Nuwave\Lighthouse\Schema\Directives\Args;


use Illuminate\Database\Eloquent\Builder;
use Nuwave\Lighthouse\Schema\Directives\BaseDirective;
use Nuwave\Lighthouse\Support\Contracts\ArgDirectiveForArray;
use Nuwave\Lighthouse\Support\Contracts\ArgFilterDirective;

/**
 * Class OrderBy
 *
 * @package App\Http\Graphql\Directives
 */
class OrderByDirective implements ArgFilterDirective, ArgDirectiveForArray
{

    /**
     * @param  \Illuminate\Database\Query\Builder|\Illuminate\Database\Eloquent\Builder $builder
     * @param  string                                                                   $columnName
     * @param  mixed                                                                    $value
     *
     * @return \Illuminate\Database\Query\Builder|\Illuminate\Database\Eloquent\Builder
     */
    public function applyFilter($builder, string $columnName, $value)
    {
        foreach ($value as $orderBy)
        {
            $builder->orderBy($orderBy['field'], $orderBy['order']);
        }

        return $builder;
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

    /**
     * Name of the directive.
     *
     * @return string
     */
    public function name(): string
    {
        return 'orderBy';
    }
}
