<?php declare(strict_types=1);

namespace Nuwave\Lighthouse\Schema\Directives;

use GraphQL\Deferred;
use GraphQL\Executor\Promise\Adapter\SyncPromise;
use Illuminate\Database\Eloquent\Model;
use Nuwave\Lighthouse\Execution\BatchLoader\BatchLoaderRegistry;
use Nuwave\Lighthouse\Execution\BatchLoader\RelationBatchLoader;
use Nuwave\Lighthouse\Execution\ModelsLoader\ModelsLoader;
use Nuwave\Lighthouse\Execution\ResolveInfo;
use Nuwave\Lighthouse\Schema\Values\FieldValue;
use Nuwave\Lighthouse\Support\Contracts\FieldMiddleware;
use Nuwave\Lighthouse\Support\Contracts\GraphQLContext;
use Tests\Utils\Models\Role;

abstract class WithRelationDirective extends BaseDirective implements FieldMiddleware
{
    use RelationDirectiveHelpers;

    /** @param  array<string, mixed>  $args */
    abstract protected function modelsLoader(mixed $parent, array $args, GraphQLContext $context, ResolveInfo $resolveInfo): ModelsLoader;

    public function handleField(FieldValue $fieldValue): void
    {
        $fieldValue->wrapResolver(
            fn (callable $resolver): \Closure => fn (Model $parent, array $args, GraphQLContext $context, ResolveInfo $resolveInfo): SyncPromise => $this
                ->loadRelation($parent, $args, $context, $resolveInfo)
                ->then(function (mixed $result) use ($resolver, $parent, $args, $context, $resolveInfo): mixed {
                    $this->storeLoadedResult($parent, $result);

                    return $resolver($parent, $args, $context, $resolveInfo);
                }),
        );
    }

    /**
     * Store the batch loaded result on the parent the resolver receives.
     *
     * The batch loader keeps a single instance per model key and loads onto that one only,
     * so another instance of the same model reached elsewhere in the result would miss it.
     */
    protected function storeLoadedResult(Model $parent, mixed $result): void {}

    /** @param  array<string, mixed>  $args */
    protected function loadRelation(Model $parent, array $args, GraphQLContext $context, ResolveInfo $resolveInfo): Deferred
    {
        $relationBatchLoader = BatchLoaderRegistry::instance(
            $this->qualifyPath($args, $resolveInfo),
            fn (): RelationBatchLoader => new RelationBatchLoader($this->modelsLoader($parent, $args, $context, $resolveInfo)),
        );

        return $relationBatchLoader->load($parent);
    }
}
