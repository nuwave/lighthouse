<?php

namespace Nuwave\Lighthouse\Support\Contracts;

interface FieldBuilderDirective extends Directive
{
    /**
     * Add additional constraints to the builder.
     *
     * @param  \Illuminate\Database\Query\Builder|\Illuminate\Database\Eloquent\Builder|\Illuminate\Database\Eloquent\Model|\Illuminate\Database\Eloquent\Relations\Relation $builder The builder used to resolve the field.
     * @return \Illuminate\Database\Query\Builder|\Illuminate\Database\Eloquent\Builder|\Illuminate\Database\Eloquent\Model|\Illuminate\Database\Eloquent\Relations\Relation The modified builder.
     */
    public function handleFieldBuilder(object $builder): object;
}
