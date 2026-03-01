<?php declare(strict_types=1);

namespace Nuwave\Lighthouse\Support\Contracts;

use Illuminate\Contracts\Database\Query\Builder;

interface ArgBuilderDirective extends Directive
{
    /**
     * Add additional constraints to the builder based on the given argument value.
     *
     * TODO try adding a generic type parameter for the type of model when PHPStan handles it better
     *
     * @param  \Illuminate\Contracts\Database\Query\Builder  $builder the builder used to resolve the field
     * @param  mixed  $value the client given value of the argument
     *
     * @return \Illuminate\Contracts\Database\Query\Builder the modified builder
     */
    public function handleBuilder(Builder $builder, mixed $value): Builder;
}
