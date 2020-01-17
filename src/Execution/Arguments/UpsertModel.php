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
        /** @var \Nuwave\Lighthouse\Execution\Arguments\Argument|null $id */
        $id = $args->arguments['id']
            ?? $args->arguments[$model->getKeyName()]
            ?? null;

        if ($id) {
            $model = $model->newQuery()->find($id->value)
                ?? $model->newInstance();
        } else {
            $model = $model->newInstance();
        }

        return ($this->previous)($model, $args);
    }
}
