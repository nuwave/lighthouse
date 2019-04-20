<?php

namespace Nuwave\Lighthouse\Schema\Directives;

use Nuwave\Lighthouse\Support\Contracts\ArgBuilderDirective;

class NotInDirective extends BaseDirective implements ArgBuilderDirective
{
    /**
     * Name of the directive.
     *
     * @return string
     */
    public function name(): string
    {
        return 'notIn';
    }

    /**
     * Apply a simple "WHERE NOT IN $values" clause.
     *
     * @param  \Illuminate\Database\Query\Builder|\Illuminate\Database\Eloquent\Builder  $builder
     * @param  mixed  $values
     * @return \Illuminate\Database\Query\Builder|\Illuminate\Database\Eloquent\Builder
     */
    public function handleBuilder($builder, $values)
    {
        return $builder->whereNotIn(
            $this->directiveArgValue('key', $this->definitionNode->name->value),
            $values
        );
    }
}
