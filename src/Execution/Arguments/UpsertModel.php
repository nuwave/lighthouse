<?php

namespace Nuwave\Lighthouse\Execution\Arguments;

use Illuminate\Support\Arr;
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
        $id = Arr::get($args->arguments, 'id', Arr::get($args->arguments, $model->getKeyName()));

        $model = $model->newQuery()->find(optional($id)->value)
            ?? $model->newInstance();

        return ($this->previous)($model, $args);
    }
}
