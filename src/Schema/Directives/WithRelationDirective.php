<?php

namespace Nuwave\Lighthouse\Schema\Directives;

use Closure;
use GraphQL\Deferred;
use GraphQL\Type\Definition\ResolveInfo;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\Relation;
use Nuwave\Lighthouse\Execution\BatchLoader\BatchLoaderRegistry;
use Nuwave\Lighthouse\Execution\BatchLoader\RelationBatchLoader;
use Nuwave\Lighthouse\Execution\ModelsLoader\ModelsLoader;
use Nuwave\Lighthouse\Schema\Values\FieldValue;
use Nuwave\Lighthouse\Support\Contracts\GraphQLContext;

abstract class WithRelationDirective extends BaseDirective
{
    /**
     * The name of the relation to be loaded.
     */
    abstract protected function relation(): string;

    abstract protected function relationLoader(ResolveInfo $resolveInfo): ModelsLoader;

    /**
     * Eager load a relation on the parent instance.
     */
    public function handleField(FieldValue $fieldValue, Closure $next): FieldValue
    {
        $previousResolver = $fieldValue->getResolver();

        $fieldValue->setResolver(
            function (Model $parent, array $args, GraphQLContext $context, ResolveInfo $resolveInfo) use ($previousResolver) {
                return $this
                    ->loadRelation($parent, $args, $resolveInfo)
                    ->then(function () use ($previousResolver, $parent, $args, $context, $resolveInfo) {
                        return $previousResolver($parent, $args, $context, $resolveInfo);
                    });
            }
        );

        return $next($fieldValue);
    }

    /**
     * @param array<string, mixed> $args
     */
    protected function loadRelation(Model $parent, array $args, ResolveInfo $resolveInfo): Deferred
    {
        // Includes the field we are loading the relation for
        $path = $resolveInfo->path;

        // In case we have no args, we can combine eager loads that are the same
        if ($args === []) {
            array_pop($path);
        }

        $path = $this->qualifyPath($path);

        /** @var \Nuwave\Lighthouse\Execution\BatchLoader\RelationBatchLoader $relationBatchLoader */
        $relationBatchLoader = BatchLoaderRegistry::instance(
            $path,
            function () use ($resolveInfo): RelationBatchLoader {
                return new RelationBatchLoader($this->relationLoader($resolveInfo));
            }
        );

        return $relationBatchLoader->load($parent);
    }

    /**
     * @return \Closure(object): void
     */
    protected function makeBuilderDecorator(ResolveInfo $resolveInfo): Closure
    {
        return function (object $builder) use ($resolveInfo): void {
            if ($builder instanceof Relation) {
                $builder = $builder->getQuery();
            }

            $resolveInfo->argumentSet->enhanceBuilder(
                $builder,
                $this->scopes()
            );
        };
    }

    /**
     * @return mixed|null
     */
    protected function scopes()
    {
        return $this->directiveArgValue('scopes', []);
    }

    /**
     * @param array<int, int|string> $path
     * @return array<int, int|string>
     */
    protected function qualifyPath(array $path): array
    {
        // Each relation must be loaded separately
        $path [] = $this->relation();

        // Scopes influence the result of the query
        return array_merge($path, $this->scopes());
    }
}
