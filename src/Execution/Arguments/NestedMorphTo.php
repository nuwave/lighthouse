<?php

namespace Nuwave\Lighthouse\Execution\Arguments;

use Nuwave\Lighthouse\Support\Contracts\ArgResolver;

class NestedMorphTo implements ArgResolver
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
        /** @var \Illuminate\Database\Eloquent\Relations\MorphTo $relation */
        $relation = $parent->{$this->relationName}();

        // TODO implement create and update once we figure out how to do polymorphic input types https://github.com/nuwave/lighthouse/issues/900

        if (isset($args->arguments['connect'])) {
            $connectArgs = $args->arguments['connect']->value;

            $morphToModel = $relation->createModelByType(
                (string) $connectArgs->arguments['type']->value
            );
            $morphToModel->setAttribute(
                $morphToModel->getKeyName(),
                $connectArgs->arguments['id']->value
            );

            $relation->associate($morphToModel);
        }

        NestedBelongsTo::disconnectOrDelete($relation, $args);
    }
}
