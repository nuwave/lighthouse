<?php declare(strict_types=1);

namespace Nuwave\Lighthouse\Support\Contracts;

use Illuminate\Database\Eloquent\Builder as EloquentBuilder;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Database\Query\Builder as QueryBuilder;
use Nuwave\Lighthouse\Execution\ResolveInfo;

interface FieldBuilderDirective extends Directive
{
    /**
     * Add additional constraints to the builder.
     *
     * TODO try adding a generic type parameter for the type of model when PHPStan handles it better
     *
     * @param  \Illuminate\Database\Query\Builder|\Illuminate\Database\Eloquent\Builder<\Illuminate\Database\Eloquent\Model>|\Illuminate\Database\Eloquent\Relations\Relation<\Illuminate\Database\Eloquent\Model>  $builder  the builder used to resolve the field
     * @param  array<string, mixed>  $args  the arguments that were passed into the field
     *
     * @return \Illuminate\Database\Query\Builder|\Illuminate\Database\Eloquent\Builder<\Illuminate\Database\Eloquent\Model>|\Illuminate\Database\Eloquent\Relations\Relation<\Illuminate\Database\Eloquent\Model> the modified builder
     */
    public function handleFieldBuilder(QueryBuilder|EloquentBuilder|Relation $builder, mixed $root, array $args, GraphQLContext $context, ResolveInfo $resolveInfo): QueryBuilder|EloquentBuilder|Relation;
}
