<?php

namespace Nuwave\Lighthouse\Schema\Directives;

use Closure;
use GraphQL\Deferred;
use GraphQL\Type\Definition\ResolveInfo;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\Relation;
use Nuwave\Lighthouse\Execution\DataLoader\BatchLoaderRegistry;
use Nuwave\Lighthouse\Execution\DataLoader\RelationAggregateBatchLoader;
use Nuwave\Lighthouse\Execution\DataLoader\RelationAggregateLoader;
use Nuwave\Lighthouse\Schema\Values\FieldValue;
use Nuwave\Lighthouse\Support\Contracts\GraphQLContext;

abstract class WithRelationAggregateDirective extends BaseDirective
{
    /**
     * The name of the relation to be loaded.
     */
    abstract protected function relationName(): string;

    /**
     * The name of the column to be loaded.
     */
    abstract protected function relationColumn(): string;

    abstract protected function relationAggregateLoader(ResolveInfo $resolveInfo): RelationAggregateLoader;

    /**
     * Eager load a relation on the parent instance.
     */
    public function handleField(FieldValue $fieldValue, Closure $next): FieldValue
    {
        $previousResolver = $fieldValue->getResolver();

        $fieldValue->setResolver(
            function (Model $parent, array $args, GraphQLContext $context, ResolveInfo $resolveInfo) use ($previousResolver) {
                return $this
                    ->loadRelation($parent, $resolveInfo)
                    ->then(function () use ($previousResolver, $parent, $args, $context, $resolveInfo) {
                        return $previousResolver($parent, $args, $context, $resolveInfo);
                    });
            }
        );

        return $next($fieldValue);
    }

    protected function loadRelation(Model $parent, ResolveInfo $resolveInfo): Deferred
    {
        $relationName = $this->relationName();
        $relationColumn = $this->relationColumn();

        // There might be multiple directives on the same field, so we differentiate by relation too
        $uniquePath = $resolveInfo->path;
        $uniquePath [] = $relationName;
        $uniquePath [] = $relationColumn;

        /** @var \Nuwave\Lighthouse\Execution\DataLoader\RelationAggregateBatchLoader $relationAggregateBatchLoader */
        $relationAggregateBatchLoader = BatchLoaderRegistry::instance(RelationAggregateBatchLoader::class, $uniquePath);

        if (! $relationAggregateBatchLoader->hasRelationAggregateLoader()) {
            $relationAggregateBatchLoader->registerRelationAggregateLoader($this->relationAggregateLoader($resolveInfo), $relationName, $relationColumn);
        }

        return $relationAggregateBatchLoader->load($parent);
    }

    /**
     * Decorate the builder used to fetch the models.
     *
     * @return Closure(object): void
     */
    protected function decorateBuilder(ResolveInfo $resolveInfo): Closure
    {
        return function (object $builder) use ($resolveInfo): void {
            if ($builder instanceof Relation) {
                $builder = $builder->getQuery();
            }

            $resolveInfo->argumentSet->enhanceBuilder(
                $builder,
                $this->directiveArgValue('scopes', [])
            );
        };
    }
}
