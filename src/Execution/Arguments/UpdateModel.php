<?php

namespace Nuwave\Lighthouse\Execution\Arguments;

use Nuwave\Lighthouse\Support\Contracts\ArgResolver;

class UpdateModel implements ArgResolver
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
        $id = $args->arguments['id']
            ?? $args->arguments[$model->getKeyName()];

        $model = $model->newQuery()->findOrFail($id->value);

        return ($this->previous)($model, $args);
    }
}
