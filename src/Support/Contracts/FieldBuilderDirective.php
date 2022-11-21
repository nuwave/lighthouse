<?php

namespace Nuwave\Lighthouse\Support\Contracts;

use GraphQL\Type\Definition\ResolveInfo;

interface FieldBuilderDirective extends Directive
{
    /**
     * Add additional constraints to the builder.
     *
     * @param  \Illuminate\Database\Query\Builder|\Illuminate\Database\Eloquent\Builder  $builder  the builder used to resolve the field
     * @param  array<string, mixed>  $args
     * @param  \Nuwave\Lighthouse\Support\Contracts\GraphQLContext  $context
     *
     * @return \Illuminate\Database\Query\Builder|\Illuminate\Database\Eloquent\Builder the modified builder
     */
    public function handleFieldBuilder(object $builder, $root, array $args, GraphQLContext $context, ResolveInfo $resolveInfo): object;
}
