<?php

namespace Nuwave\Lighthouse\Support\Contracts;

interface ArgBuilderDirective extends Directive
{
    /**
     * Add additional constraints to the builder based on the given argument value.
     *
     * @template TModel of \Illuminate\Database\Eloquent\Model
     *
     * @param  \Illuminate\Database\Query\Builder|\Illuminate\Database\Eloquent\Builder<TModel>  $builder  the builder used to resolve the field
     * @param  mixed  $value  the client given value of the argument
     *
     * @return \Illuminate\Database\Query\Builder|\Illuminate\Database\Eloquent\Builder<TModel> the modified builder
     */
    public function handleBuilder($builder, $value);
}
