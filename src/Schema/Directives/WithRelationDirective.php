<?php

namespace Nuwave\Lighthouse\Schema\Directives;

use Closure;
use GraphQL\Deferred;
use GraphQL\Type\Definition\ResolveInfo;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\Relation;
use Nuwave\Lighthouse\Execution\DataLoader\BatchLoaderRegistry;
use Nuwave\Lighthouse\Execution\DataLoader\RelationBatchLoader;
use Nuwave\Lighthouse\Execution\DataLoader\RelationLoader;
use Nuwave\Lighthouse\Schema\Values\FieldValue;
use Nuwave\Lighthouse\Support\Contracts\GraphQLContext;

abstract class WithRelationDirective extends BaseDirective
{
    /**
     * The name of the relation to be loaded.
     */
    abstract protected function relationName(): string;

    abstract protected function relationLoader(ResolveInfo $resolveInfo): RelationLoader;

    /**
     * Eager load a relation on the parent instance.
     */
    public function handleField(FieldValue $fieldValue, Closure $next): FieldValue
    {
        $previousResolver = $fieldValue->getResolver();

        return $next(
            $fieldValue->setResolver(
                function (Model $parent, array $args, GraphQLContext $context, ResolveInfo $resolveInfo) use ($previousResolver) {
                    return $this
                        ->loadRelation($parent, $resolveInfo)
                        ->then(function () use ($previousResolver, $parent, $args, $context, $resolveInfo) {
                            return $previousResolver($parent, $args, $context, $resolveInfo);
                        });
                }
            )
        );
    }

    protected function loadRelation(Model $parent, ResolveInfo $resolveInfo): Deferred
    {
        $relationName = $this->relationName();

        // There might be multiple directives on the same field, so we differentiate by relation too
        $uniquePath = $resolveInfo->path;
        $uniquePath [] = $relationName;

        /** @var \Nuwave\Lighthouse\Execution\DataLoader\RelationBatchLoader $relationBatchLoader */
        $relationBatchLoader = BatchLoaderRegistry::instance(RelationBatchLoader::class, $uniquePath);

        if (! $relationBatchLoader->hasRelationLoader()) {
            $relationBatchLoader->registerRelationLoader($this->relationLoader($resolveInfo), $relationName);
        }

        return $relationBatchLoader->load($parent);
    }

    /**
     * Decorate the builder used to fetch the models.
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
