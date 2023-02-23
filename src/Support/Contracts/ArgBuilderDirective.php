<?php declare(strict_types=1);

namespace Nuwave\Lighthouse\Support\Contracts;

interface ArgBuilderDirective extends Directive
{
    /**
     * Add additional constraints to the builder based on the given argument value.
     *
     * TODO try adding a generic type parameter for the type of model when PHPStan handles it better
     *
     * @param  \Illuminate\Database\Query\Builder|\Illuminate\Database\Eloquent\Builder<\Illuminate\Database\Eloquent\Model>  $builder  the builder used to resolve the field
     * @param  mixed  $value  the client given value of the argument
     *
     * @return \Illuminate\Database\Query\Builder|\Illuminate\Database\Eloquent\Builder<\Illuminate\Database\Eloquent\Model> the modified builder
     */
    public function handleBuilder($builder, $value);
}
