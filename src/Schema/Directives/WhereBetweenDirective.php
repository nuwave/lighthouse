<?php

namespace Nuwave\Lighthouse\Schema\Directives;

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
        $table = $builder->getModel()->getTable();

        return $builder->whereBetween(
            $this->directiveArgValue('key', $table.'.'.$this->definitionNode->name->value),
            $values
        );
    }
}
