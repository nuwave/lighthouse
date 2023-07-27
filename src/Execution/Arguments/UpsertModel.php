<?php declare(strict_types=1);

namespace Nuwave\Lighthouse\Execution\Arguments;

use Nuwave\Lighthouse\Support\Contracts\ArgResolver;

class UpsertModel implements ArgResolver
{
    /** @var callable|\Nuwave\Lighthouse\Support\Contracts\ArgResolver */
    protected $previous;

    /** @var array<string> */
    protected array $identifyingColumns;

    /** @param  callable|\Nuwave\Lighthouse\Support\Contracts\ArgResolver  $previous */
    public function __construct(callable $previous, ?array $identifyingColumns)
    {
        $this->previous = $previous;
        $this->identifyingColumns = $identifyingColumns ?? [];
    }

    /**
     * @param  \Illuminate\Database\Eloquent\Model  $model
     * @param  \Nuwave\Lighthouse\Execution\Arguments\ArgumentSet  $args
     */
    public function __invoke($model, $args): mixed
    {
        // TODO consider Laravel native ->upsert(), available from 8.10
        $existingModel = null;

        if (!empty($this->identifyingColumns)) {
            $existingModel = $model
                ->newQuery()
                ->firstWhere(
                array_intersect_key(
                        $args->toArray(),
                        array_flip($this->identifyingColumns)
                    )
                );

            if ($existingModel !== null) {
                $model = $existingModel;
            }
        }

        if ($existingModel === null) {
            $id = $args->arguments['id']
                ?? $args->arguments[$model->getKeyName()]
                ?? null;

            if ($id !== null) {
                $existingModel = $model
                    ->newQuery()
                    ->find($id->value);

                if ($existingModel !== null) {
                    $model = $existingModel;
                }
            }
        }

        return ($this->previous)($model, $args);
    }
}
