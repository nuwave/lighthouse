<?php

namespace Nuwave\Lighthouse\Support\Contracts;

interface ArgBuilderDirective extends ArgDirective
{
    /**
     *
     *
     * @param  \Illuminate\Database\Query\Builder|\Illuminate\Database\Eloquent\Builder  $builder
     * @param  mixed  $value
     * @return \Illuminate\Database\Query\Builder|\Illuminate\Database\Eloquent\Builder
     */
    public function handleBuilder($builder, $value);
}
