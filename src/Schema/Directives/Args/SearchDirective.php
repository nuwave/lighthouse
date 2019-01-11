<?php

namespace Nuwave\Lighthouse\Schema\Directives\Args;

use Nuwave\Lighthouse\Schema\Directives\BaseDirective;
use Nuwave\Lighthouse\Support\Contracts\ArgFilterDirective;

class SearchDirective extends BaseDirective implements ArgFilterDirective
{
    /**
     * Name of the directive.
     *
     * @return string
     */
    public function name(): string
    {
        return 'search';
    }

    /**
     * @param  \Illuminate\Database\Query\Builder|\Illuminate\Database\Eloquent\Builder  $builder
     * @param  string  $columnName
     * @param  mixed  $value
     *
     * @return \Laravel\Scout\Builder
     */
    public function applyFilter($builder, string $columnName, $value)
    {
        $within = $this->directiveArgValue('within');

        /** @var \Illuminate\Database\Eloquent\Model $modelClass */
        $modelClass = get_class(
            $builder->getModel()
        );

        /** @var \Laravel\Scout\Builder $builder */
        $builder = $modelClass::search($value);

        if ($within !== null) {
            $builder->within($within);
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
}
