<?php

namespace Nuwave\Lighthouse\Execution\Arguments;

use Illuminate\Support\Arr;
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
     */
    public function __invoke($parent, $args): void
    {
        /** @var \Illuminate\Database\Eloquent\Relations\BelongsToMany|\Illuminate\Database\Eloquent\Relations\MorphToMany $relation */
        $relation = $parent->{$this->relationName}();

        if ($args->has('sync')) {
            $relation->sync(
                $this->generateRelationArray($args->arguments['sync'])
            );
        }

        if ($args->has('syncWithoutDetaching')) {
            $relation->syncWithoutDetaching(
                $this->generateRelationArray($args->arguments['syncWithoutDetaching'])
            );
        }

        NestedOneToMany::createUpdateUpsert($args, $relation);

        if ($args->has('delete')) {
            $ids = $args->arguments['delete']->toPlain();

            $relation->detach($ids);
            $relation->getRelated()::destroy($ids);
        }

        if ($args->has('connect')) {
            $relation->attach(
                $this->generateRelationArray($args->arguments['connect'])
            );
        }

        if ($args->has('disconnect')) {
            $relation->detach(
                $args->arguments['disconnect']->toPlain()
            );
        }
    }

    /**
     * Generate an array for passing into sync, syncWithoutDetaching or connect method.
     *
     * Those functions natively have the capability of passing additional
     * data to store in the pivot table. That array expects passing the id's
     * as keys, so we transform the passed arguments to match that.
     *
     * @param  \Nuwave\Lighthouse\Execution\Arguments\Argument $args
     * @return mixed[]
     */
    private function generateRelationArray(Argument $args): array
    {
        $values = $args->toPlain();

        if (empty($values)) {
            return [];
        }

        // Since GraphQL inputs are monomorphic, we can just look at the first
        // given value and can deduce the value of all given args.
        $exemplaryValue = $values[0];

        // We assume that the values contain pivot information
        if (is_array($exemplaryValue)) {
            $relationArray = [];
            foreach ($values as $value) {
                $id = Arr::pull($value, 'id');
                $relationArray[$id] = $value;
            }

            return $relationArray;
        }

        // The default case is simply a flat array of IDs which we don't have to transform
        return $values;
    }
}
