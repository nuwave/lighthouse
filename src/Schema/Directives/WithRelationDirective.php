<?php

namespace Nuwave\Lighthouse\Schema\Directives;

use Closure;
use GraphQL\Deferred;
use GraphQL\Type\Definition\ResolveInfo;
use Illuminate\Database\Eloquent\Model;
use Nuwave\Lighthouse\Execution\DataLoader\BatchLoader;
use Nuwave\Lighthouse\Schema\Values\FieldValue;
use Nuwave\Lighthouse\Support\Contracts\GraphQLContext;

abstract class WithRelationDirective extends BaseDirective
{
    /**
     * Eager load a relation on the parent instance.
     */
    public function handleField(FieldValue $fieldValue, Closure $next): FieldValue
    {
        return $next(
            $fieldValue->setResolver(
                $this->deferResolver(
                    $fieldValue->getResolver()
                )
            )
        );
    }

    /**
     * The name of the batch loader to use.
     */
    abstract public function batchLoaderName(): string;

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
     *
     * @param  callable $resolver
     */
    protected function deferResolver($resolver): Closure
    {
        return function (Model $parent, array $args, GraphQLContext $context, ResolveInfo $resolveInfo) use ($resolver): Deferred {
            return new Deferred(function () use ($resolver, $parent, $args, $context, $resolveInfo) {
                return $this->loader($resolveInfo)
                    ->load($parent->getKey(), ['parent' => $parent])
                    ->then(
                        function () use ($resolver, $parent, $args, $context, $resolveInfo) {
                            return $resolver($parent, $args, $context, $resolveInfo);
                        }
                    );
            });
        };
    }

    /**
     * Create an instance of RelationBatchLoader loader to apply counts.
     */
    protected function loader(ResolveInfo $resolveInfo): BatchLoader
    {
        return BatchLoader::instance( // @phpstan-ignore-line TODO remove when updating graphql-php
            $this->batchLoaderName(),
            $resolveInfo->path,
            [
                'relationName' => $this->relationName(),
                'decorateBuilder' => $this->decorateBuilder($resolveInfo),
            ]
        );
    }

    /**
     * The the name of the relation to be loaded.
     */
    public function relationName(): string
    {
        return $this->directiveArgValue('relation', $this->nodeName());
    }
}
