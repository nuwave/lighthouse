<?php

namespace Nuwave\Lighthouse\Execution\Arguments;

use GraphQL\Error\Error;
use Illuminate\Support\Arr;
use Nuwave\Lighthouse\Support\Contracts\ArgResolver;

class UpdateModel implements ArgResolver
{
    const MISSING_PRIMARY_KEY_FOR_UPDATE = 'Missing primary key for update.';
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
        /** @var \Nuwave\Lighthouse\Execution\Arguments\Argument|null $id */
        $id = Arr::pull($args->arguments, 'id')
            ?? Arr::pull($args->arguments, $model->getKeyName())
            ?? null;

        if ($id === null) {
            throw new Error(self::MISSING_PRIMARY_KEY_FOR_UPDATE);
        }

        $model = $model->newQuery()->findOrFail($id->value);

        return ($this->previous)($model, $args);
    }
}
