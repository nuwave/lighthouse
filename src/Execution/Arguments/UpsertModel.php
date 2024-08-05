<?php declare(strict_types=1);

namespace Nuwave\Lighthouse\Execution\Arguments;

use Nuwave\Lighthouse\Support\Contracts\ArgResolver;

class UpsertModel implements ArgResolver
{
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
        // TODO consider Laravel native ->upsert(), available from 8.10
        $id = $args->arguments['id']
            ?? $args->arguments[$model->getKeyName()]
            ?? null;

        $idValue = $id?->value;
        if ($idValue) {
            $existingModel = $model->newQuery()
                ->find($idValue);

            if ($existingModel !== null) {
                $model = $existingModel;
            }
        }

        return ($this->previous)($model, $args);
    }
}
