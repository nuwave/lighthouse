<?php

namespace Nuwave\Lighthouse\Schema\Directives;

use Closure;
use GraphQL\Deferred;
use GraphQL\Type\Definition\ResolveInfo;
use Illuminate\Database\Eloquent\Model;
use Nuwave\Lighthouse\Execution\DataLoader\BatchLoader;
use Nuwave\Lighthouse\Execution\Utils\ModelKey;
use Nuwave\Lighthouse\Schema\Values\FieldValue;
use Nuwave\Lighthouse\Support\Contracts\GraphQLContext;

abstract class WithRelationDirective extends BaseDirective
{
    /**
     * The fully-qualified class name of the batch loader to use.
     *
     * @return class-string<\Nuwave\Lighthouse\Execution\DataLoader\BatchLoader>
     */
    abstract protected function batchLoaderClass(): string;

    /**
     * The name of the relation to be loaded.
     */
    abstract protected function relationName(): string;

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

    /**
     * Decorate the builder used to fetch the models.
     */
    protected function decorateBuilder(ResolveInfo $resolveInfo): Closure
    {
        return function ($query) use ($resolveInfo) {
            $resolveInfo->argumentSet->enhanceBuilder(
                $query,
                $this->directiveArgValue('scopes', [])
            );
        };
    }

    /**
     * Return a new deferred resolver.
     */
    protected function deferredRelationResolver(callable $resolver): Closure
    {
        return function (?Model $parent, array $args, GraphQLContext $context, ResolveInfo $resolveInfo) use ($resolver): Deferred {
            return new Deferred(function () use ($resolver, $parent, $args, $context, $resolveInfo) {
                if (is_null($parent)) {
                    return $resolver($parent, $args, $context, $resolveInfo);
                }

                return $this->loader($resolveInfo)
                    ->load(
                        ModelKey::build($parent),
                        ['parent' => $parent]
                    )
                    ->then(function () use ($resolver, $parent, $args, $context, $resolveInfo) {
                        return $resolver($parent, $args, $context, $resolveInfo);
                    });
            });
        };
    }

    /**
     * Create an instance of RelationBatchLoader loader.
     */
    protected function loader(ResolveInfo $resolveInfo): BatchLoader
    {
        return BatchLoader::instance( // @phpstan-ignore-line TODO remove when updating graphql-php
            $this->batchLoaderClass(),
            $resolveInfo->path,
            [
                'relationName' => $this->relationName(),
                'decorateBuilder' => $this->decorateBuilder($resolveInfo),
            ]
        );
    }
}
