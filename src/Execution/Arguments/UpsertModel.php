<?php

namespace Nuwave\Lighthouse\Execution\Arguments;

use Nuwave\Lighthouse\Support\Contracts\ArgResolver;

class UpsertModel implements ArgResolver
{
    /**
     * @var callable|\Nuwave\Lighthouse\Support\Contracts\ArgResolver
     */
    private $previous;

    /**
     * @param callable|\Nuwave\Lighthouse\Support\Contracts\ArgResolver $previous
     */
    public function __construct(callable $previous)
    {
        $this->previous = $previous;
    }

    /**
     * @param  \Illuminate\Database\Eloquent\Model  $model
     * @param  \Nuwave\Lighthouse\Execution\Arguments\ArgumentSet  $args
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
                $model = $existingModel;
            }
        }

        return ($this->previous)($model, $args);
    }
}
