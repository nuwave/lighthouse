<?php

namespace Nuwave\Lighthouse\Execution\Arguments;

use Nuwave\Lighthouse\Support\Contracts\ArgResolver;

class NestedManyToMany implements ArgResolver
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
        /** @var \Illuminate\Database\Eloquent\Relations\BelongsToMany|\Illuminate\Database\Eloquent\Relations\MorphToMany $relation */
        $relation = $parent->{$this->relationName}();

        if (isset($args->arguments['sync'])) {
            $relation->sync(
                $args->arguments['sync']->toPlain()
            );
        }

        if (isset($args->arguments['syncWithoutDetaching'])) {
            $relation->syncWithoutDetaching(
                $args->arguments['syncWithoutDetaching']->toPlain()
            );
        }

        NestedOneToMany::createUpdateUpsert($args, $relation);

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
