<?php

namespace Nuwave\Lighthouse\Execution\DataLoader;

use Illuminate\Database\Eloquent\Model;
use Nuwave\Lighthouse\Execution\Utils\ModelKey;

class RelationBatchLoader
{
    /**
     * A map from relation names and meta information about them.
     *
     * @var array<string, \Nuwave\Lighthouse\Execution\DataLoader\RelationMeta>
     */
    protected $relations = [];

    public function relation(string $relationName, RelationMeta $relationMeta)
    {
        // TODO what happens if the name exists? throw, bail, overwrite?

        $this->relations[$relationName] = $relationMeta;
    }

    /**
     * Eager-load the relations.
     *
     * @return array<string, mixed>
     */
    public function resolve(): array
    {
        // TODO split paginated and non-paginated relations
        $relation = [$this->relations => $this->decorateBuilder];

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
        $parts = explode('.', $this->relations);

        // We just return the first level of relations for now. They
        // hold the nested relations in case they are needed.
        $firstRelation = $parts[0];

        return $model->getRelation($firstRelation);
    }
}
