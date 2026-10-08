<?php declare(strict_types=1);

namespace Nuwave\Lighthouse\Support\Contracts;

use Illuminate\Contracts\Database\Query\Builder;
use Nuwave\Lighthouse\Execution\ResolveInfo;

interface FieldBuilderDirective extends Directive
{
    /**
     * Add additional constraints to the builder.
     *
     * TODO try adding a generic type parameter for the type of model when PHPStan handles it better
     *
     * @param  \Illuminate\Contracts\Database\Query\Builder  $builder  the builder used to resolve the field
     * @param  array<string, mixed>  $args  the arguments that were passed into the field
     *
     * @return \Illuminate\Contracts\Database\Query\Builder the modified builder
     */
    public function handleFieldBuilder(Builder $builder, mixed $root, array $args, GraphQLContext $context, ResolveInfo $resolveInfo): Builder;
}
