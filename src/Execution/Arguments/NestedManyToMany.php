<?php

namespace Nuwave\Lighthouse\Execution\Arguments;

use Nuwave\Lighthouse\Execution\ArgumentResolver;

class NestedManyToMany implements ArgumentResolver
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
        /** @var \Illuminate\Database\Eloquent\Relations\BelongsToMany|\Illuminate\Database\Eloquent\Relations\MorphToMany $relation */
        $relation = $model->{$this->relationName}();

        if (isset($args->arguments['sync'])) {
            $relation->sync(
                $args->arguments['sync']->toPlain()
            );
        }

        /* @var \Nuwave\Lighthouse\Execution\Arguments\Argument|null $create */
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

        if (isset($args->arguments['delete'])) {
            $ids = $args->arguments['delete']->toPlain();

            $relation->detach($ids);
            $relation->getRelated()::destroy($ids);
        }

        if (isset($args->arguments['connect'])) {
            $relation->attach(
                $args->arguments['connect']->toPlain()
            );
        }

        if (isset($args->arguments['disconnect'])) {
            $relation->detach(
                $args->arguments['disconnect']->toPlain()
            );
        }
    }
}
