<?php

namespace Nuwave\Lighthouse\Schema\Directives;

use Nuwave\Lighthouse\Support\Contracts\ArgBuilderDirective;

class NeqDirective extends BaseDirective implements ArgBuilderDirective
{
    /**
     * Name of the directive.
     *
     * @return string
     */
    public function name(): string
    {
        return 'neq';
    }

    /**
     * Apply a "WHERE <> $value" clause.
     *
     * @param  \Illuminate\Database\Query\Builder|\Illuminate\Database\Eloquent\Builder  $builder
     * @param  mixed  $value
     * @return \Illuminate\Database\Query\Builder|\Illuminate\Database\Eloquent\Builder
     */
    public function handleBuilder($builder, $value)
    {
        return $builder->where(
            $this->directiveArgValue('key', $this->definitionNode->name->value),
            '<>',
            $value
        );
    }
}
