<?php

namespace Nuwave\Lighthouse\Support\Contracts;

interface ArgBuilderDirective extends ArgDirective
{
    /**
     * Add additional constraints to the builder based on the given argument value.
     *
     * @param  \Illuminate\Database\Query\Builder|\Illuminate\Database\Eloquent\Builder|\Illuminate\Database\Eloquent\Relations\Relation|\Laravel\Scout\Builder  $builder  The builder used to resolve the field.
     * @param  mixed  $value  The client given value of the argument.
     * @return \Illuminate\Database\Query\Builder|\Illuminate\Database\Eloquent\Builder|\Illuminate\Database\Eloquent\Relations\Relation|\Laravel\Scout\Builder  The modified builder.
     */
    public function handleBuilder($builder, $value);
}
