<?php

namespace Nuwave\Lighthouse\Execution\Arguments;

use Nuwave\Lighthouse\Execution\ArgumentResolver;

class NestedMorphTo implements ArgumentResolver
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
        /** @var \Illuminate\Database\Eloquent\Relations\MorphTo $relation */
        $relation = $model->{$this->relationName}();

        // TODO implement create and update once we figure out how to do polymorphic input types https://github.com/nuwave/lighthouse/issues/900

        if (isset($args->arguments['connect'])) {
            $connectArgs = $args->arguments['connect']->value;

            $morphToModel = $relation->createModelByType(
                (string) $connectArgs->arguments['type']
            );
            $morphToModel->setAttribute(
                $morphToModel->getKeyName(),
                $connectArgs->arguments['id']
            );

            $relation->associate($morphToModel);
        }

        NestedBelongsTo::disconnectOrDelete($relation, $args);
    }
}
