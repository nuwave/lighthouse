<?php

namespace Nuwave\Lighthouse\Support\Contracts;

interface ArgBuilderDirective extends ArgDirective
{
    /**
     * Add additional constraints to the builder based on the given argument value.
     *
     * @param  \Illuminate\Database\Query\Builder|\Illuminate\Database\Eloquent\Builder  $builder
     * @param  mixed  $value
     * @return \Illuminate\Database\Query\Builder|\Illuminate\Database\Eloquent\Builder
     */
    public function handleBuilder($builder, $value);
}
