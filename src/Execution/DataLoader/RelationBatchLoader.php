<?php

namespace Nuwave\Lighthouse\Execution\DataLoader;

use Illuminate\Database\Eloquent\Model;
use Nuwave\Lighthouse\Execution\Utils\ModelKey;

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
     * @var \Nuwave\Lighthouse\Pagination\PaginationArgs|null
     */
    protected $paginationArgs;

    /**
     * @param  \Closure  $decorateBuilder
     * @param  \Nuwave\Lighthouse\Pagination\PaginationArgs  $paginationArgs
     */
    public function __construct(
        string $relationName,
        // Not using a type-hint to avoid resolving those params through the container
        $decorateBuilder,
        $paginationArgs = null
    ) {
        $this->relationName = $relationName;
        $this->decorateBuilder = $decorateBuilder;
        $this->paginationArgs = $paginationArgs;
    }

    /**
     * Eager-load the relation.
     *
     * @return array<string, mixed>
     */
    public function resolve(): array
    {
        $relation = [$this->relationName => $this->decorateBuilder];

        if ($this->paginationArgs !== null) {
            $modelRelationFetcher = new ModelRelationFetcher(
                RelationFetcher::extractParentModels($this->keys),
                $relation
            );
            $models = $modelRelationFetcher->loadRelationsForPage($this->paginationArgs);
        } else {
            $models = RelationFetcher::loadedParentModels($this->keys, $relation);
        }

        return $models
            ->mapWithKeys(
                /**
                 * @return array<string, mixed>
                 */
                function (Model $model): array {
                    return [ModelKey::build($model) => $this->extractRelation($model)];
                }
            )
            ->all();
    }

    /**
     * Extract the relation that was loaded.
     *
     * @return mixed The model's relation.
     */
    protected function extractRelation(Model $model)
    {
        // Dot notation may be used to eager load nested relations
        $parts = explode('.', $this->relationName);

        // We just return the first level of relations for now. They
        // hold the nested relations in case they are needed.
        $firstRelation = $parts[0];

        return $model->getRelation($firstRelation);
    }
}
