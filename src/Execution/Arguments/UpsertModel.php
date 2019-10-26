<?php

namespace Nuwave\Lighthouse\Execution\Arguments;

use Nuwave\Lighthouse\Execution\ArgumentResolver;

class UpsertModel implements ArgumentResolver
{
    /**
     * @var \Closure|\Nuwave\Lighthouse\Execution\ArgumentResolver
     */
    private $previous;

    /**
     * ArgResolver constructor.
     * @param \Closure|\Nuwave\Lighthouse\Execution\ArgumentResolver $previous
     */
    public function __construct($previous)
    {
        $this->previous = $previous;
    }

    public function __invoke($model, ArgumentSet $args)
    {
        $id = $args->arguments['id']
            ?? $args->arguments[$model->getKeyName()];

        $model = $model->newQuery()->find($id->value)
            ?? $model->newInstance();

        return ($this->previous)($model, $args);
    }
}
