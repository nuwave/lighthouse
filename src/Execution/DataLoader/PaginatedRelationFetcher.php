<?php

namespace Nuwave\Lighthouse\Execution\DataLoader;

use Closure;
use Illuminate\Database\Eloquent\Builder as EloquentBuilder;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;
use Nuwave\Lighthouse\Pagination\PaginationArgs;
use ReflectionClass;
use ReflectionMethod;

class PaginatedRelationFetcher implements RelationFetcher
{
    /**
     * @var \Closure
     */
    protected $decorateBuilder;

    /**
     * @var \Nuwave\Lighthouse\Pagination\PaginationArgs
     */
    protected $paginationArgs;

    public function __construct(Closure $decorateBuilder, PaginationArgs $paginationArgs)
    {
        $this->decorateBuilder = $decorateBuilder;
        $this->paginationArgs = $paginationArgs;
    }

    public function fetch(EloquentCollection $parents, string $relationName): void
    {
        RelationCountFetcher::loadCount($parents, [$relationName => $this->decorateBuilder]);
        $this->loadRelationForPage($parents, $relationName);
    }

    /**
     * Load one page of relations of all the models.
     *
     * The relation will be converted to a `Paginator` instance.
     */
    protected function loadRelationForPage(EloquentCollection $parents, string $relationName): void
    {
        $relationBuilders = $this->initializeRelationBuilders($parents, $relationName);

        $relatedModels = $this->loadRelatedModels($relationBuilders);

        $this->hydratePivotRelation($parents, $relationName, $relatedModels);
        $this->loadDefaultWith($relatedModels);
        $this->associateRelationModels($parents, $relationName, $relatedModels);
        $this->convertRelationToPaginator($parents, $relationName);
    }

    /**
     * Return a fresh instance of a query builder for the underlying model.
     */
    protected function newModelQuery(EloquentCollection $parents): EloquentBuilder
    {
        /** @var \Illuminate\Database\Eloquent\Model $anyModelInstance */
        $anyModelInstance = $parents->first();

        /** @var \Illuminate\Database\Eloquent\Builder $newModelQuery */
        $newModelQuery = $anyModelInstance->newModelQuery();

        return $newModelQuery;
    }

    /**
     * Get queries to fetch relationships.
     *
     * @return \Illuminate\Support\Collection<\Illuminate\Database\Eloquent\Relations\Relation>
     */
    protected function initializeRelationBuilders(EloquentCollection $parents, string $relationName): Collection {
        return $parents
            ->toBase()
            ->map(function (Model $model) use ($parents, $relationName): Relation {
                $relation = $this->relationInstance($parents, $relationName);

                $relation->addEagerConstraints([$model]);

                ($this->decorateBuilder)($relation, $model);

                if (method_exists($relation, 'shouldSelect')) {
                    $shouldSelect = new ReflectionMethod(get_class($relation), 'shouldSelect');
                    $shouldSelect->setAccessible(true);
                    $select = $shouldSelect->invoke($relation, ['*']);

                    // @phpstan-ignore-next-line Relation&Builder mixin not recognized
                    $relation->addSelect($select);
                } elseif (method_exists($relation, 'getSelectColumns')) {
                    $getSelectColumns = new ReflectionMethod(get_class($relation), 'getSelectColumns');
                    $getSelectColumns->setAccessible(true);
                    $select = $getSelectColumns->invoke($relation, ['*']);

                    // @phpstan-ignore-next-line Relation&Builder mixin not recognized
                    $relation->addSelect($select);
                }

                $relation->initRelation([$model], $relationName);

                /** @var \Illuminate\Database\Eloquent\Relations\Relation&\Illuminate\Database\Eloquent\Builder $relation */
                return $relation->forPage($this->paginationArgs->page, $this->paginationArgs->first);
            });
    }

    protected function loadDefaultWith(EloquentCollection $collection): void
    {
        /** @var \Illuminate\Database\Eloquent\Model|null $model */
        $model = $collection->first();
        if ($model === null) {
            return;
        }

        $reflection = new ReflectionClass($model);
        $withProperty = $reflection->getProperty('with');
        $withProperty->setAccessible(true);

        $unloadedWiths = array_filter(
            (array) $withProperty->getValue($model),
            static function (string $relation) use ($model): bool {
                return ! $model->relationLoaded($relation);
            }
        );

        if (count($unloadedWiths) > 0) {
            $collection->load($unloadedWiths);
        }
    }

    /**
     * This is the name that Eloquent gives to the attribute that contains the count.
     *
     * @see \Illuminate\Database\Eloquent\Concerns\QueriesRelationships::withCount()
     */
    protected function relationCountName(string $relationName): string
    {
        return Str::snake("{$relationName}_count");
    }

    /**
     * @param  \Illuminate\Support\Collection<\Illuminate\Database\Eloquent\Relations\Relation>  $relations
     */
    protected function loadRelatedModels(Collection $relations): EloquentCollection
    {
        // Merge all the relation queries into a single query with UNION ALL.
        // We have to make sure to use ->getQuery() in order to respect
        // model scopes, such as soft deletes
        $mergedRelationQuery = $relations->reduce(
            static function (EloquentBuilder $builder, Relation $relation): EloquentBuilder {
                return $builder->unionAll(
                // @phpstan-ignore-next-line Laravel is not that strictly typed
                    $relation->getQuery()
                );
            },
            // Use the first query as the initial starting point
            $relations->shift()->getQuery()
        );

        return $mergedRelationQuery->get();
    }

    protected function convertRelationToPaginator(EloquentCollection $parents, string $relationName): void
    {
        foreach ($parents as $model) {
            $total = $model->getAttribute(
                $this->relationCountName($relationName)
            );

            $paginator = app()->makeWith(
                LengthAwarePaginator::class,
                [
                    'items' => $model->getRelation($relationName),
                    'total' => $total,
                    'perPage' => $this->paginationArgs->first,
                    'currentPage' => $this->paginationArgs->page,
                    'options' => [],
                ]
            );

            $model->setRelation($relationName, $paginator);
        }
    }

    /**
     * Associate the collection of all fetched relationModels back with their parents.
     */
    protected function associateRelationModels(EloquentCollection $parents, string $relationName, EloquentCollection $relatedModels): void
    {
        $this
            ->relationInstance($parents, $relationName)
            ->match(
                $parents->all(),
                $relatedModels,
                $relationName
            );
    }

    /**
     * Ensure the pivot relation is hydrated too, if it exists.
     *
     * @param  \Illuminate\Database\Eloquent\Collection<\Illuminate\Database\Eloquent\Model>  $relatedModels
     */
    protected function hydratePivotRelation(EloquentCollection $parents, string $relationName, EloquentCollection $relatedModels): void
    {
        $relation = $this->relationInstance($parents, $relationName);

        if ($relatedModels->isNotEmpty() && method_exists($relation, 'hydratePivotRelation')) {
            $hydrationMethod = new ReflectionMethod(get_class($relation), 'hydratePivotRelation');
            $hydrationMethod->setAccessible(true);
            $hydrationMethod->invoke($relation, $relatedModels->all());
        }
    }

    /**
     * Use the underlying model to instantiate a relation by name.
     */
    protected function relationInstance(EloquentCollection $parents, string $relationName): Relation
    {
        return $this
            ->newModelQuery($parents)
            ->getRelation($relationName);
    }
}
