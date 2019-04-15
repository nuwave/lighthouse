<?php

namespace Nuwave\Lighthouse\Between;

use Nuwave\Lighthouse\Schema\Directives\BaseDirective;
use Nuwave\Lighthouse\Support\Contracts\ArgBuilderDirective;

class WhereBetweenDirective extends BaseDirective implements ArgBuilderDirective
{
    const NAME = 'whereBetween';

    /**
     * Name of the directive.
     *
     * @return string
     */
    public function name(): string
    {
        return self::NAME;
    }

    /**
     * Apply a "WHERE BETWEEN" clause.
     *
     * @param  \Illuminate\Database\Query\Builder|\Illuminate\Database\Eloquent\Builder  $builder
     * @param  mixed  $values
     * @return \Illuminate\Database\Query\Builder|\Illuminate\Database\Eloquent\Builder
     */
    public function handleBuilder($builder, $values)
    {
        return $builder->whereBetween(
            $this->directiveArgValue('key', $this->definitionNode->name->value),
            $values
        );
    }
}
