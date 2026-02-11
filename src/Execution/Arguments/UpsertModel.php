<?php declare(strict_types=1);

namespace Nuwave\Lighthouse\Execution\Arguments;

use GraphQL\Error\Error;
use Illuminate\Database\Eloquent\Builder as EloquentBuilder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\Relation;
use Nuwave\Lighthouse\Support\Contracts\ArgResolver;

class UpsertModel implements ArgResolver
{
    public const MISSING_IDENTIFYING_COLUMNS_FOR_UPSERT = 'All configured identifying columns must be present and non-null for upsert.';

    public const CANNOT_UPSERT_UNRELATED_MODEL = 'Cannot upsert a model that is not related to the given parent.';

    /** @var callable|\Nuwave\Lighthouse\Support\Contracts\ArgResolver */
    protected $previous;

    /**
     * @param  callable|\Nuwave\Lighthouse\Support\Contracts\ArgResolver  $previous
     * @param  array<string>|null  $identifyingColumns
     */
    public function __construct(
        callable $previous,
        protected ?array $identifyingColumns = null,
        /** @var \Illuminate\Database\Eloquent\Relations\Relation<\Illuminate\Database\Eloquent\Model>|null $parentRelation */
        protected ?Relation $parentRelation = null,
    ) {
        $this->previous = $previous;
    }

    /**
     * @param  \Illuminate\Database\Eloquent\Model  $model
     * @param  ArgumentSet  $args
     */
    public function __invoke($model, $args): mixed
    {
        // TODO consider Laravel native ->upsert(), available from 8.10
        $existingModel = null;

        if ($this->identifyingColumns) {
            $identifyingColumns = $this->identifyingColumnValues($args, $this->identifyingColumns)
                ?? throw new Error(self::MISSING_IDENTIFYING_COLUMNS_FOR_UPSERT);

            $existingModel = $this->queryBuilder($model)->firstWhere($identifyingColumns);
            if (
                $existingModel === null
                && $this->parentRelation !== null
                && $model->newQuery()->where($identifyingColumns)->exists()
            ) {
                throw new Error(self::CANNOT_UPSERT_UNRELATED_MODEL);
            }

            if ($existingModel !== null) {
                $model = $existingModel;
            }
        }

        if ($existingModel === null) {
            $id = $this->retrieveID($model, $args);
            if ($id) {
                $existingModel = $this->queryBuilder($model)->find($id);
                if (
                    $existingModel === null
                    && $this->parentRelation !== null
                    && $model->newQuery()->find($id) !== null
                ) {
                    throw new Error(self::CANNOT_UPSERT_UNRELATED_MODEL);
                }

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

        foreach ($identifyingValues as $identifyingColumn) {
            if ($identifyingColumn === null) {
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

    /** @return \Illuminate\Database\Eloquent\Builder<\Illuminate\Database\Eloquent\Model> */
    protected function queryBuilder(Model $model): EloquentBuilder
    {
        return $this->parentRelation?->getQuery()
            ?? $model->newQuery();
    }
}
