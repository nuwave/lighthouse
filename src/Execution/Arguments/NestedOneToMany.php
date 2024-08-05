<?php declare(strict_types=1);

namespace Nuwave\Lighthouse\Execution\Arguments;

use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Nuwave\Lighthouse\Support\Contracts\ArgResolver;

class NestedOneToMany implements ArgResolver
{
    public function __construct(
        protected string $relationName,
    ) {}

    /**
     * @param  \Illuminate\Database\Eloquent\Model  $model
     * @param  ArgumentSet  $args
     */
    public function __invoke($model, $args): void
    {
        $relation = $model->{$this->relationName}();
        assert($relation instanceof HasMany || $relation instanceof MorphMany);

        if ($args->has('create')) {
            $saveModel = new ResolveNested(new SaveModel($relation));

            foreach ($args->arguments['create']->value as $childArgs) {
                // @phpstan-ignore-next-line Relation&Builder mixin not recognized
                $saveModel($relation->make(), $childArgs);
            }
        }

        if ($args->has('update')) {
            $updateModel = new ResolveNested(new UpdateModel(new SaveModel($relation)));

            foreach ($args->arguments['update']->value as $childArgs) {
                // @phpstan-ignore-next-line Relation&Builder mixin not recognized
                $updateModel($relation->make(), $childArgs);
            }
        }

        if ($args->has('upsert')) {
            $upsertModel = new ResolveNested(new UpsertModel(new SaveModel($relation)));

            foreach ($args->arguments['upsert']->value as $childArgs) {
                // @phpstan-ignore-next-line Relation&Builder mixin not recognized
                $upsertModel($relation->make(), $childArgs);
            }
        }

        if ($args->has('connect')) {
            $children = $relation
                ->make()
                ->whereIn(
                    $relation->make()->getKeyName(),
                    $args->arguments['connect']->value,
                )
                ->get();
            $relation->saveMany($children);
        }

        if ($args->has('disconnect')) {
            $children = $relation
                ->make()
                ->whereIn(
                    $relation->make()->getKeyName(),
                    $args->arguments['disconnect']->value,
                )
                ->get();
            foreach ($children as $child) {
                $child->setAttribute($relation->getForeignKeyName(), null);
                $child->save();
            }
        }

        if ($args->has('delete')) {
            $relation->getRelated()::destroy(
                $args->arguments['delete']->toPlain(),
            );
        }
    }
}
