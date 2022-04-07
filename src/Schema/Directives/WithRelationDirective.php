<?php

namespace Nuwave\Lighthouse\Schema\Directives;

use Closure;
use GraphQL\Deferred;
use GraphQL\Type\Definition\ResolveInfo;
use Illuminate\Database\Eloquent\Model;
use Nuwave\Lighthouse\Execution\BatchLoader\BatchLoaderRegistry;
use Nuwave\Lighthouse\Execution\BatchLoader\RelationBatchLoader;
use Nuwave\Lighthouse\Execution\ModelsLoader\ModelsLoader;
use Nuwave\Lighthouse\Schema\Values\FieldValue;
use Nuwave\Lighthouse\Support\Contracts\FieldMiddleware;
use Nuwave\Lighthouse\Support\Contracts\GraphQLContext;

abstract class WithRelationDirective extends BaseDirective implements FieldMiddleware
{
    use RelationDirectiveHelpers;

    abstract protected function modelsLoader(ResolveInfo $resolveInfo): ModelsLoader;

    public function handleField(FieldValue $fieldValue, Closure $next): FieldValue
    {
        $previousResolver = $fieldValue->getResolver();

        $fieldValue->setResolver(function (Model $parent, array $args, GraphQLContext $context, ResolveInfo $resolveInfo) use ($previousResolver) {
            return $this
                ->loadRelation($parent, $args, $resolveInfo)
                ->then(static function () use ($previousResolver, $parent, $args, $context, $resolveInfo) {
                    return $previousResolver($parent, $args, $context, $resolveInfo);
                });
        });

        return $next($fieldValue);
    }

    /**
     * @param  array<string, mixed>  $args
     */
    protected function loadRelation(Model $parent, array $args, ResolveInfo $resolveInfo): Deferred
    {
        /** @var \Nuwave\Lighthouse\Execution\BatchLoader\RelationBatchLoader $relationBatchLoader */
        $relationBatchLoader = BatchLoaderRegistry::instance(
            $this->qualifyPath($args, $resolveInfo),
            function () use ($resolveInfo): RelationBatchLoader {
                return new RelationBatchLoader($this->modelsLoader($resolveInfo));
            }
        );

        return $relationBatchLoader->load($parent);
    }
}
