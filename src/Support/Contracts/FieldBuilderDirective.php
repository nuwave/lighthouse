<?php

namespace Nuwave\Lighthouse\Support\Contracts;

interface FieldBuilderDirective extends Directive
{
    /**
     * Add additional constraints to the builder.
     *
     * @param  \Illuminate\Database\Query\Builder|\Illuminate\Database\Eloquent\Builder  $builder  the builder used to resolve the field
     *
     * @return \Illuminate\Database\Query\Builder|\Illuminate\Database\Eloquent\Builder the modified builder
     */
    public function handleFieldBuilder(object $builder): object;
}
