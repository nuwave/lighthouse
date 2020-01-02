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
                $this->generateRelationArray($args->arguments['sync'])
            );
        }

        if (isset($args->arguments['syncWithoutDetaching'])) {
            $relation->syncWithoutDetaching(
                $this->generateRelationArray($args->arguments['syncWithoutDetaching'])
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
                $this->generateRelationArray($args->arguments['connect'])
            );
        }

        if (isset($args->arguments['disconnect'])) {
            $relation->detach(
                $args->arguments['disconnect']->toPlain()
            );
        }
    }

    /**
     * @param  \Nuwave\Lighthouse\Execution\Arguments\Argument $args
     *
     * @return array
     */
    private function generateRelationArray(Argument $args)
    {
        $values = $args->toPlain();

        if (empty($values)) {
            return [];
        }

        if (! is_array($values[0])) {
            // first values isn't array. Assume values are simply IDs, and return them (old behaviour)
            return $values;
        } else {
            // assume values are arrays and contains pivot information
            $relationArray = [];
            foreach ($values as $value) {
                $id = $value['id'];
                unset($value['id']);
                $relationArray[$id] = $value;
            }

            return $relationArray;
        }
    }
}
