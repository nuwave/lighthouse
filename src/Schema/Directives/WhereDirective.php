<?php

namespace Nuwave\Lighthouse\Schema\Directives;

use Nuwave\Lighthouse\Support\Contracts\ArgBuilderDirective;

class WhereDirective extends BaseDirective implements ArgBuilderDirective
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
     * Add any "WHERE" clause to the builder.
     *
     * @param  \Illuminate\Database\Query\Builder|\Illuminate\Database\Eloquent\Builder  $builder
     * @param  mixed  $value
     * @return \Illuminate\Database\Query\Builder|\Illuminate\Database\Eloquent\Builder
     */
    public function handleBuilder($builder, $value)
    {
        // Allow users to overwrite the default "where" clause, e.g. "whereYear"
        $clause = $this->directiveArgValue('clause', 'where');

        return $builder->{$clause}(
            $this->directiveArgValue('key', $this->definitionNode->name->value),
            $operator = $this->directiveArgValue('operator', '='),
            $value
        );
    }
}
