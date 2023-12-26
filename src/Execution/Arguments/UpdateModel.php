<?php declare(strict_types=1);

namespace Nuwave\Lighthouse\Execution\Arguments;

use GraphQL\Error\Error;
use Illuminate\Support\Arr;
use Nuwave\Lighthouse\Support\Contracts\ArgResolver;

class UpdateModel implements ArgResolver
{
    public const MISSING_PRIMARY_KEY_FOR_UPDATE = 'Missing primary key for update.';

    /** @var callable|\Nuwave\Lighthouse\Support\Contracts\ArgResolver */
    protected $previous;

    /** @param  callable|\Nuwave\Lighthouse\Support\Contracts\ArgResolver  $previous */
    public function __construct(callable $previous)
    {
        $this->previous = $previous;
    }

    /**
     * @param  \Illuminate\Database\Eloquent\Model  $model
     * @param  ArgumentSet  $args
     */
    public function __invoke($model, $args): mixed
    {
        $id = Arr::pull($args->arguments, 'id')
            ?? Arr::pull($args->arguments, $model->getKeyName())
            ?? throw new Error(self::MISSING_PRIMARY_KEY_FOR_UPDATE);
        assert($id instanceof Argument);

        $model = $model->newQuery()->findOrFail($id->value);

        return ($this->previous)($model, $args);
    }
}
