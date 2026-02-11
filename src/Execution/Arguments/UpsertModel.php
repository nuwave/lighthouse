<?php declare(strict_types=1);

namespace Nuwave\Lighthouse\Execution\Arguments;

use GraphQL\Error\Error;
use Illuminate\Database\Eloquent\Model;
use Nuwave\Lighthouse\Support\Contracts\ArgResolver;

use function Safe\array_flip;

class UpsertModel implements ArgResolver
{
    public const MISSING_IDENTIFYING_COLUMNS_FOR_UPSERT = 'All configured identifying columns must be present and non-null for upsert.';

    /** @var callable|\Nuwave\Lighthouse\Support\Contracts\ArgResolver */
    protected $previous;

    /** @param  callable|\Nuwave\Lighthouse\Support\Contracts\ArgResolver  $previous */
    public function __construct(
        callable $previous,
        /** @var array<string>|null */
        protected ?array $identifyingColumns = null,
    ) {
        $this->previous = $previous;
    }

    /**
     * @param  \Illuminate\Database\Eloquent\Model  $model
     * @param  ArgumentSet  $args
     */
    public function __invoke($model, $args): mixed
    {
        // Do not use Laravel native upsert() here, as it bypasses model hydration and model events.
        $existingModel = null;

        if ($this->identifyingColumns) {
            $identifyingColumns = $this->identifyingColumnValues($args, $this->identifyingColumns)
                ?? throw new Error(self::MISSING_IDENTIFYING_COLUMNS_FOR_UPSERT);

            $existingModel = $model->newQuery()->firstWhere($identifyingColumns);

            if ($existingModel !== null) {
                $model = $existingModel;
            }
        }

        if ($existingModel === null) {
            $id = $this->retrieveID($model, $args);
            if ($id) {
                $existingModel = $model->newQuery()->find($id);
                if ($existingModel !== null) {
                    $model = $existingModel;
                }
            }
        }

        return ($this->previous)($model, $args);
    }

    /**
     * @param  array<int, string>  $identifyingColumns
     *
     * @return array<string, mixed>|null
     */
    protected function identifyingColumnValues(ArgumentSet $args, array $identifyingColumns): ?array
    {
        $identifyingValues = array_intersect_key(
            $args->toArray(),
            array_flip($identifyingColumns),
        );

        if (count($identifyingValues) !== count($identifyingColumns)) {
            return null;
        }

        foreach ($identifyingValues as $identifyingValue) {
            if ($identifyingValue === null) {
                return null;
            }
        }

        return $identifyingValues;
    }

    /** @return mixed The value of the ID or null */
    protected function retrieveID(Model $model, ArgumentSet $args)
    {
        foreach (['id', $model->getKeyName()] as $key) {
            if (! isset($args->arguments[$key])) {
                continue;
            }

            $id = $args->arguments[$key]->value;
            if ($id) {
                return $id;
            }

            // Prevent passing along empty IDs that would be filled into the model
            unset($args->arguments[$key]);
        }

        return null;
    }
}
