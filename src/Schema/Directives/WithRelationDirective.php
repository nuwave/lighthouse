<?php

namespace Nuwave\Lighthouse\Schema\Directives;

use Closure;
use GraphQL\Type\Definition\ResolveInfo;
use Illuminate\Database\Eloquent\Model;
use Nuwave\Lighthouse\Execution\DataLoader\LoaderRegistry;
use Nuwave\Lighthouse\Execution\DataLoader\RelationBatchLoader;
use Nuwave\Lighthouse\Execution\DataLoader\RelationFetcher;
use Nuwave\Lighthouse\Schema\Values\FieldValue;
use Nuwave\Lighthouse\Support\Contracts\GraphQLContext;

abstract class WithRelationDirective extends BaseDirective
{
    /**
     * The name of the relation to be loaded.
     */
    abstract protected function relationName(): string;

    abstract protected function relationFetcher(ResolveInfo $resolveInfo): RelationFetcher;

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

    protected function deferredRelationResolver(callable $resolver): Closure
    {
        return function (Model $parent, array $args, GraphQLContext $context, ResolveInfo $resolveInfo) use ($resolver) {
            $loader = $this->loader($resolveInfo);

            $relationName = $this->relationName();
            if (! $loader->hasRelation($relationName)) {
                $loader->registerRelation($relationName, $this->relationFetcher($resolveInfo));
            }

            return $loader
                ->relation($relationName, $parent)
                ->then(function () use ($resolver, $parent, $args, $context, $resolveInfo) {
                    return $resolver($parent, $args, $context, $resolveInfo);
                });
        };
    }

    protected function loader(ResolveInfo $resolveInfo): RelationBatchLoader
    {
        /** @var \Nuwave\Lighthouse\Execution\DataLoader\RelationBatchLoader $relationBatchLoader */
        $relationBatchLoader = LoaderRegistry::instance(
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
