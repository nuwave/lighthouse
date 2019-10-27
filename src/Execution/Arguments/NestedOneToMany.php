<?php

namespace Nuwave\Lighthouse\Execution\Arguments;

use Illuminate\Database\Eloquent\Relations\Relation;
use Nuwave\Lighthouse\Execution\ArgumentResolver;

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

    public function __invoke($model, ArgumentSet $args)
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
            $saveModel = new ArgResolver(new SaveModel($relation));

            foreach ($args->arguments['create']->value as $childArgs) {
                $saveModel($relation->make(), $childArgs);
            }
        }

        if (isset($args->arguments['update'])) {
            $updateModel = new ArgResolver(new UpdateModel(new SaveModel($relation)));

            foreach ($args->arguments['update']->value as $childArgs) {
                $updateModel($relation->make(), $childArgs);
            }
        }

        if (isset($args->arguments['upsert'])) {
            $upsertModel = new ArgResolver(new UpsertModel(new SaveModel($relation)));

            foreach ($args->arguments['upsert']->value as $childArgs) {
                $upsertModel($relation->make(), $childArgs);
            }
        }
    }
}
