<?php

namespace Nuwave\Lighthouse\Execution\Arguments;

use Nuwave\Lighthouse\Execution\ArgumentResolver;
use Illuminate\Database\Eloquent\Relations\Relation;

class NestedOneToMany implements ArgumentResolver
{
    /**
     * @var string
     */
    private $relationName;

    public function __construct(string $relationName)
    {
        $this->relationName = $relationName;
    }

    /**
     * @param  \Illuminate\Database\Eloquent\Model  $model
     * @param  \Nuwave\Lighthouse\Execution\Arguments\ArgumentSet  $args
     * @return void
     */
    public function __invoke($model, $args)
    {
        /** @var \Illuminate\Database\Eloquent\Relations\HasMany|\Illuminate\Database\Eloquent\Relations\MorphMany $relation */
        $relation = $model->{$this->relationName}();

        static::createUpdateUpsert($args, $relation);

        if (isset($args->arguments['delete'])) {
            $relation->getRelated()::destroy(
                $args->arguments['delete']->toPlain()
            );
        }
    }

    /**
     * @param  \Nuwave\Lighthouse\Execution\Arguments\ArgumentSet  $args
     * @param  \Illuminate\Database\Eloquent\Relations\Relation  $relation
     */
    public static function createUpdateUpsert(ArgumentSet $args, Relation $relation): void
    {
        if (isset($args->arguments['create'])) {
            $saveModel = new ResolveNested(new SaveModel($relation));

            foreach ($args->arguments['create']->value as $childArgs) {
                $saveModel($relation->make(), $childArgs);
            }
        }

        if (isset($args->arguments['update'])) {
            $updateModel = new ResolveNested(new UpdateModel(new SaveModel($relation)));

            foreach ($args->arguments['update']->value as $childArgs) {
                $updateModel($relation->make(), $childArgs);
            }
        }

        if (isset($args->arguments['upsert'])) {
            $upsertModel = new ResolveNested(new UpsertModel(new SaveModel($relation)));

            foreach ($args->arguments['upsert']->value as $childArgs) {
                $upsertModel($relation->make(), $childArgs);
            }
        }
    }
}
