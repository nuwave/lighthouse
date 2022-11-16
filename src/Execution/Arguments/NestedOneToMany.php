<?php

namespace Nuwave\Lighthouse\Execution\Arguments;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOneOrMany;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Database\Eloquent\Relations\Relation;
use Nuwave\Lighthouse\Support\Contracts\ArgResolver;

class NestedOneToMany implements ArgResolver
{
    /**
     * @var string
     */
    protected $relationName;

    public function __construct(string $relationName)
    {
        $this->relationName = $relationName;
    }

    /**
     * @param  \Illuminate\Database\Eloquent\Model  $parent
     * @param  \Nuwave\Lighthouse\Execution\Arguments\ArgumentSet  $args
     */
    public function __invoke($parent, $args): void
    {
        $relation = $parent->{$this->relationName}();
        assert($relation instanceof HasMany || $relation instanceof MorphMany);

        static::createUpdateUpsert($args, $relation);
        static::connectDisconnect($args, $relation);

        if ($args->has('delete')) {
            $relation->getRelated()::destroy(
                $args->arguments['delete']->toPlain()
            );
        }
    }

    public static function createUpdateUpsert(ArgumentSet $args, Relation $relation): void
    {
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
    }

    public static function connectDisconnect(ArgumentSet $args, HasOneOrMany $relation): void
    {
        if ($args->has('connect')) {
            // @phpstan-ignore-next-line Relation&Builder mixin not recognized
            $children = $relation
                ->make()
                ->whereIn(
                    self::getKeyName($relation),
                    $args->arguments['connect']->value
                )
                ->get();

            // @phpstan-ignore-next-line Relation&Builder mixin not recognized
            $relation->saveMany($children);
        }

        if ($args->has('disconnect')) {
            // @phpstan-ignore-next-line Relation&Builder mixin not recognized
            $children = $relation
                ->make()
                ->whereIn(
                    self::getKeyName($relation),
                    $args->arguments['disconnect']->value
                )
                ->get();

            foreach ($children as $child) {
                assert($child instanceof Model);
                $child->setAttribute($relation->getForeignKeyName(), null);
                $child->save();
            }
        }
    }

    /**
     * TODO remove this horrible hack when we no longer support Laravel 5.6.
     */
    protected static function getKeyName(HasOneOrMany $relation): string
    {
        $getKeyName = \Closure::bind(
            function () {
                // @phpstan-ignore-next-line This is a dirty hack
                return $this->make()->getKeyName();
            },
            $relation,
            get_class($relation)
        );

        return $getKeyName();
    }
}
