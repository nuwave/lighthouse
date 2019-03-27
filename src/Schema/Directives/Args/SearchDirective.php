<?php

namespace Nuwave\Lighthouse\Schema\Directives\Args;

use Nuwave\Lighthouse\Schema\Directives\BaseDirective;
use Nuwave\Lighthouse\Support\Contracts\ArgBuilderDirective;

class SearchDirective extends BaseDirective implements ArgBuilderDirective
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
     * @param  mixed  $value
     * @return \Laravel\Scout\Builder
     */
    public function handleBuilder($builder, $value)
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
}
