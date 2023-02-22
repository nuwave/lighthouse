<?php

namespace Nuwave\Lighthouse\Support\Contracts;

use Nuwave\Lighthouse\Execution\ResolveInfo;

interface FieldBuilderDirective extends Directive
{
    /**
     * Add additional constraints to the builder.
     *
     * @template TModel of \Illuminate\Database\Eloquent\Model
     *
     * @param  \Illuminate\Database\Query\Builder|\Illuminate\Database\Eloquent\Builder<TModel>  $builder  the builder used to resolve the field
     * @param  array<string, mixed>  $args  the arguments that were passed into the field
     *
     * @return \Illuminate\Database\Query\Builder|\Illuminate\Database\Eloquent\Builder<TModel> the modified builder
     */
    public function handleFieldBuilder(object $builder, $root, array $args, GraphQLContext $context, ResolveInfo $resolveInfo): object;
}
