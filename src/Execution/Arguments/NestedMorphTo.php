<?php

namespace Nuwave\Lighthouse\Execution\Arguments;

use Illuminate\Database\Eloquent\Relations\MorphTo;
use Nuwave\Lighthouse\Support\Contracts\ArgResolver;

class NestedMorphTo implements ArgResolver
{
    /**
     * @var \Illuminate\Database\Eloquent\Relations\MorphTo
     */
    protected $relation;

    public function __construct(MorphTo $relation)
    {
        $this->relation = $relation;
    }

    /**
     * @param  \Illuminate\Database\Eloquent\Model  $parent
     * @param  \Nuwave\Lighthouse\Execution\Arguments\ArgumentSet  $args
     */
    public function __invoke($parent, $args): void
    {
        // TODO implement create and update once we figure out how to do polymorphic input types https://github.com/nuwave/lighthouse/issues/900

        if ($args->has('connect')) {
            $connectArgs = $args->arguments['connect']->value;

            $morphToModel = $this->relation->createModelByType(
                (string) $connectArgs->arguments['type']->value
            );
            $morphToModel->setAttribute(
                $morphToModel->getKeyName(),
                $connectArgs->arguments['id']->value
            );

            $this->relation->associate($morphToModel);
        }

        NestedBelongsTo::disconnectOrDelete($this->relation, $args);
    }
}
