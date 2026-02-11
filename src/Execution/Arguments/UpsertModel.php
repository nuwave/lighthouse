<?php declare(strict_types=1);

namespace Nuwave\Lighthouse\Execution\Arguments;

use GraphQL\Error\Error;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\Relation;
use Nuwave\Lighthouse\Support\Contracts\ArgResolver;

class UpsertModel implements ArgResolver
{
    public const CANNOT_UPSERT_UNRELATED_MODEL = 'Cannot upsert a model that is not related to the given parent.';

    /** @var callable|\Nuwave\Lighthouse\Support\Contracts\ArgResolver */
    protected $previous;

    /**
     * @param  callable|\Nuwave\Lighthouse\Support\Contracts\ArgResolver  $previous
     * @param  \Illuminate\Database\Eloquent\Relations\Relation<\Illuminate\Database\Eloquent\Model>|null  $parentRelation
     */
    public function __construct(
        callable $previous,
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

        $id = $this->retrieveID($model, $args);
        if ($id) {
            $existingModel = $this->queryBuilder($model)
                ->find($id);
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

        return ($this->previous)($model, $args);
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
    protected function queryBuilder(Model $model)
    {
        return $this->parentRelation?->getQuery()->clone()
            ?? $model->newQuery();
    }
}
