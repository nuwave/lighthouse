<?php

namespace Nuwave\Lighthouse\Execution\Arguments;

use GraphQL\Error\Error;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Arr;
use Nuwave\Lighthouse\Support\Contracts\ArgResolver;

class UpdateModel implements ArgResolver
{
    public const MISSING_PRIMARY_KEY_FOR_UPDATE = 'Missing primary key for update.';

    /**
     * @var callable|\Nuwave\Lighthouse\Support\Contracts\ArgResolver
     */
    protected $previous;

    /**
     * @param  callable|\Nuwave\Lighthouse\Support\Contracts\ArgResolver  $previous
     */
    public function __construct(callable $previous)
    {
        $this->previous = $previous;
    }

    /**
     * @param  Model  $model
     * @param  \Nuwave\Lighthouse\Execution\Arguments\ArgumentSet  $args
     */
    public function __invoke($model, $args)
    {
        $id = self::pullId($args, $model);
        $instance = $model->newQuery()->findOrFail($id);

        return ($this->previous)($instance, $args);
    }

    /**
     * Extract and remove the model ID from the given args.
     *
     * @return mixed any non-null ID value
     */
    public static function pullId(ArgumentSet $args, Model $model)
    {
        /** @var \Nuwave\Lighthouse\Execution\Arguments\Argument|null $id */
        $id = Arr::pull($args->arguments, 'id')
            ?? Arr::pull($args->arguments, $model->getKeyName())
            ?? null;

        if (null === $id) {
            throw new Error(self::MISSING_PRIMARY_KEY_FOR_UPDATE);
        }

        return $id->value;
    }

    /**
     * Extract and remove the model ID from the given args.
     *
     * @return mixed any non-null ID value
     */
    public static function getId(ArgumentSet $args, Model $model)
    {
        /** @var \Nuwave\Lighthouse\Execution\Arguments\Argument|null $id */
        $id = Arr::get($args->arguments, 'id')
            ?? Arr::get($args->arguments, $model->getKeyName())
            ?? null;

        if (null === $id) {
            throw new Error(self::MISSING_PRIMARY_KEY_FOR_UPDATE);
        }

        return $id->value;
    }
}
