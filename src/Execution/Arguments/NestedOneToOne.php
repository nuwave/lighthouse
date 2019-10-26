<?php

namespace Nuwave\Lighthouse\Execution\Arguments;

use Nuwave\Lighthouse\Execution\ArgumentResolver;

class NestedOneToOne implements ArgumentResolver
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
        /** @var \Illuminate\Database\Eloquent\Relations\HasOne|\Illuminate\Database\Eloquent\Relations\MorphOne $relation */
        $relation = $model->{$this->relationName}();

        /* @var \Nuwave\Lighthouse\Execution\Arguments\Argument|null $create */
        if (isset($args->arguments['create'])) {
            $saveModel = new ArgResolver(new SaveModel($relation));

            $saveModel($relation->make(), $args->arguments['create']->value);
        }

        if (isset($args->arguments['update'])) {
            $updateModel = new ArgResolver(new UpdateModel(new SaveModel($relation)));

            $updateModel($relation->make(), $args->arguments['update']->value);
        }

        if (isset($args->arguments['upsert'])) {
            $upsertModel = new ArgResolver(new UpsertModel(new SaveModel($relation)));

            $upsertModel($relation->make(), $args->arguments['upsert']->value);
        }

        if (isset($args->arguments['delete'])) {
            $relation->getRelated()::destroy(
                $args->arguments['delete']->toPlain()
            );
        }
    }
}
