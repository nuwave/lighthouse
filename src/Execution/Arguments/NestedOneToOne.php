<?php

namespace Nuwave\Lighthouse\Execution\Arguments;

use Nuwave\Lighthouse\Support\Contracts\ArgResolver;

class NestedOneToOne implements ArgResolver
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
     * @param  \Illuminate\Database\Eloquent\Model  $parent
     * @param  \Nuwave\Lighthouse\Execution\Arguments\ArgumentSet  $args
     * @return void
     */
    public function __invoke($parent, $args)
    {
        /** @var \Illuminate\Database\Eloquent\Relations\HasOne|\Illuminate\Database\Eloquent\Relations\MorphOne $relation */
        $relation = $parent->{$this->relationName}();

        /** @var \Nuwave\Lighthouse\Execution\Arguments\Argument|null $create */
        if (isset($args->arguments['create'])) {
            $saveModel = new ResolveNested(new SaveModel($relation));

            $saveModel($relation->make(), $args->arguments['create']->value);
        }

        if (isset($args->arguments['update'])) {
            $updateModel = new ResolveNested(new UpdateModel(new SaveModel($relation)));

            $updateModel($relation->make(), $args->arguments['update']->value);
        }

        if (isset($args->arguments['upsert'])) {
            $upsertModel = new ResolveNested(new UpsertModel(new SaveModel($relation)));

            $upsertModel($relation->make(), $args->arguments['upsert']->value);
        }

        if (isset($args->arguments['delete'])) {
            $relation->getRelated()::destroy(
                $args->arguments['delete']->toPlain()
            );
        }
    }
}
