<?php

namespace Nuwave\Lighthouse\Execution\DataLoader;

use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Database\Eloquent\Model;

class RelationBatchLoader extends BatchLoader
{
    /**
     * The name of the Eloquent relation to load.
     *
     * @var string
     */
    protected $relationName;

    /**
     * This function is called with the relation query builder and may modify it.
     *
     * @var \Closure
     */
    protected $decorateBuilder;

    /**
     * Optionally, a relation may be paginated.
     *
     * @var \Nuwave\Lighthouse\Pagination\PaginationArgs
     */
    protected $paginationArgs;

    /**
     * @param  string  $relationName
     * @param  \Closure  $decorateBuilder
     * @param  \Nuwave\Lighthouse\Pagination\PaginationArgs  $paginationArgs
     */
    public function __construct(
        string $relationName,
        $decorateBuilder,
        $paginationArgs = null
    ) {
        $this->relationName = $relationName;
        $this->decorateBuilder = $decorateBuilder;
        $this->paginationArgs = $paginationArgs;
    }

    /**
     * Resolve the keys.
     *
     * @return mixed[]
     */
    public function resolve(): array
    {
        $relation = [$this->relationName => $this->decorateBuilder];

        if ($this->paginationArgs !== null) {
            $modelRelationFetcher = new ModelRelationFetcher(
                $this->getParentModels(),
                $relation
            );
            $models = $modelRelationFetcher->loadRelationsForPage($this->paginationArgs);
        } else {
            $models = $this->getParentModels()->load($relation);
        }

        return $models
            ->mapWithKeys(
                function (Model $model): array {
                    return [$this->buildKey($model->getKey()) => $model->getRelation($this->relationName)];
                }
            )
            ->all();
    }

    /**
     * Get the parents from the keys that are present on the BatchLoader.
     *
     * @return \Illuminate\Database\Eloquent\Collection
     */
    protected function getParentModels(): EloquentCollection
    {
        return new EloquentCollection(
            array_map(
                function (array $meta) {
                    return $meta['parent'];
                },
                $this->keys
            )
        );
    }
}
