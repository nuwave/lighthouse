<?php

namespace Nuwave\Lighthouse\Execution\Arguments;

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

        /** @var \Nuwave\Lighthouse\Execution\Arguments\Argument|null $create */
        if ($create = $args->arguments['create'] ?? null) {
            $saveModel = new ArgResolver(new SaveModel($relation));

            foreach ($create->value as $childArgs) {
                $saveModel($relation->make(), $childArgs);
            }
        }

        /** @var \Nuwave\Lighthouse\Execution\Arguments\Argument|null $update */
        if ($update = $args->arguments['update'] ?? null) {
            $updateModel = new ArgResolver(new UpdateModel(new SaveModel($relation)));

            foreach ($update->value as $childArgs) {
                $updateModel($relation->make(), $childArgs);
            }
        }

        /** @var \Nuwave\Lighthouse\Execution\Arguments\Argument|null $upsert */
        if ($upsert = $args->arguments['upsert'] ?? null) {
            $upsertModel = new ArgResolver(new UpsertModel(new SaveModel($relation)));

            foreach ($upsert->value as $childArgs) {
                $upsertModel($relation->make(), $childArgs);
            }
        }

        /** @var \Nuwave\Lighthouse\Execution\Arguments\Argument|null $delete */
        if ($delete = $args->arguments['delete'] ?? null) {
            $relation->getRelated()::destroy($delete->toPlain());
        }
    }
}
