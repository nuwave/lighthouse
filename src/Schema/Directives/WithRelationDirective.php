<?php

namespace Nuwave\Lighthouse\Schema\Directives;

use Closure;
use GraphQL\Type\Definition\ResolveInfo;
use Illuminate\Database\Eloquent\Model;
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
        return $next(
            $fieldValue->setResolver(
                $this->deferredRelationResolver(
                    $fieldValue->getResolver()
                )
            )
        );
    }

    protected function deferredRelationResolver(?callable $resolver = null): Closure
    {
        return function (Model $parent, array $args, GraphQLContext $context, ResolveInfo $resolveInfo) use ($resolver) {
            $loader = $this->loader($resolveInfo);

            $relationName = $this->relationName();
            if (! $loader->hasRelationLoader($relationName)) {
                $loader->registerRelationLoader($relationName, $this->relationLoader($resolveInfo));
            }

            $deferred = $loader->load($relationName, $parent);

            return $resolver === null
                ? $deferred
                : $deferred->then(function () use ($resolver, $parent, $args, $context, $resolveInfo) {
                    return $resolver($parent, $args, $context, $resolveInfo);
                });
        };
    }

    protected function loader(ResolveInfo $resolveInfo): RelationBatchLoader
    {
        /** @var \Nuwave\Lighthouse\Execution\DataLoader\RelationBatchLoader $relationBatchLoader */
        $relationBatchLoader = BatchLoaderRegistry::instance(
            RelationBatchLoader::class,
            $resolveInfo->path
        );

        return $relationBatchLoader;
    }

    /**
     * Decorate the builder used to fetch the models.
     */
    protected function decorateBuilder(ResolveInfo $resolveInfo): Closure
    {
        return function (object $query) use ($resolveInfo): void {
            $resolveInfo->argumentSet->enhanceBuilder(
                $query,
                $this->directiveArgValue('scopes', [])
            );
        };
    }
}
