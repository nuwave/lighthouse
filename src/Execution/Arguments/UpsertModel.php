<?php

namespace Nuwave\Lighthouse\Execution\Arguments;

use Nuwave\Lighthouse\Support\Contracts\ArgResolver;

class UpsertModel implements ArgResolver
{
    /**
     * @var \Closure|\Nuwave\Lighthouse\Support\Contracts\ArgResolver
     */
    private $previous;

    /**
     * ArgResolver constructor.
     * @param \Closure|\Nuwave\Lighthouse\Support\Contracts\ArgResolver $previous
     */
    public function __construct($previous)
    {
        $this->previous = $previous;
    }

    /**
     * @param  \Illuminate\Database\Eloquent\Model  $model
     * @param  \Nuwave\Lighthouse\Execution\Arguments\ArgumentSet  $args
     * @return void
     */
    public function __invoke($model, $args)
    {
        if (
            $id = $args->arguments['id']
                ?? $args->arguments[$model->getKeyName()]
                ?? null
        ) {
            if (
                $existingModel = $model
                    ->newQuery()
                    ->find($id->value)
            ) {
                // TODO there is a slight lingering bug here. In case the $model already
                // has a parent relationship associated with it that differs from what is
                // currently persisted, that change will be forgotten. How can we safely
                // merge the values of $model and $existingModel together? Simply taking
                // all values within $model and overwriting $existingModel could go wrong
                // if $model has default attributes that have already been changed.
                $model = $existingModel;
            }
        }

        return ($this->previous)($model, $args);
    }
}
