<?php

namespace Nuwave\Lighthouse\Execution\DataLoader;

use GraphQL\Deferred;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
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

    /**
     * A map from relation names and meta information about them.
     *
     * @var array<string, \Nuwave\Lighthouse\Execution\DataLoader\RelationMeta>
     */
    protected $relationsToCount = [];

    /**
     * A map from unique keys to parent model instances.
     *
     * @var array<string, \Illuminate\Database\Eloquent\Model>
     */
    protected $parents = [];

    /**
     * @var bool
     */
    protected $hasLoaded;

    public function hasRelationMeta(string $relationName): bool
    {
        return isset($this->relations[$relationName]);
    }

    public function registerRelationMeta(string $relationName, RelationMeta $relationMeta): void
    {
        // TODO what happens if the name exists? throw, bail, overwrite?

        $this->relations[$relationName] = $relationMeta;
    }

    public function hasRelationToCountMeta(string $relationName): bool
    {
        return isset($this->relationsToCount[$relationName]);
    }

    public function registerRelationToCountMeta(string $relationName, RelationMeta $relationMeta): void
    {
        // TODO what happens if the name exists? throw, bail, overwrite?

        $this->relationsToCount[$relationName] = $relationMeta;
    }

    public function relation(string $relationName, Model $parent): Deferred
    {
        $modelKey = ModelKey::build($parent);
        $this->parents[$modelKey] = $parent;

        return new Deferred(function () use ($parent, $relationName) {
            if (! $this->hasLoaded) {
                $this->load();
            }

            return $this->extractRelation($parent, $relationName);
        });
    }

    public function relationToCount(string $relationName, Model $parent): Deferred
    {
        $modelKey = ModelKey::build($parent);
        $this->parents[$modelKey] = $parent;

        return new Deferred(function () use ($parent, $relationName) {
            if (! $this->hasLoaded) {
                $this->load();
            }

            return $this->extractRelation($parent, $relationName);
        });
    }

    public function load(): void
    {
        $parentModels = new EloquentCollection($this->parents);
        $parentsGroupedByClass = $parentModels->groupBy(
            /**
             * @return class-string<\Illuminate\Database\Eloquent\Model>
             */
            static function (Model $model): string {
                return get_class($model);
            },
            true
        );

        foreach ($this->relations as $relation => $relationMeta) {
            // TODO split paginated and non-paginated relations, maybe do each set at once?
            $relation = [$relation => $relationMeta->decorateBuilder];

            foreach ($parentsGroupedByClass as $parentsOfSameClass) {
                $parentsOfSameClass->load($relation);
            }

            // TODO mutates the models in a destructive way
//            if ($relationMeta->paginationArgs !== null) {
//                $modelRelationFetcher = new ModelRelationFetcher($parentModels, $relation);
//                $models = $modelRelationFetcher->loadRelationsForPage($relationMeta->paginationArgs);
//            } else {
//            }
        }

        foreach ($this->relationsToCount as $relation => $relationMeta) {
            $relation = [$relation => $relationMeta->decorateBuilder];

            foreach ($parentsGroupedByClass as $parentsOfSameClass) {
                $parentsOfSameClass->loadCount($relation);
            }
        }
    }

    /**
     * Extract the relation that was loaded.
     *
     * @return mixed The model's relation.
     */
    protected function extractRelation(Model $model, string $relation)
    {
        // Dot notation may be used to eager load nested relations
        $parts = explode('.', $relation);

        // We just return the first level of relations for now. They
        // hold the nested relations in case they are needed.
        $firstRelation = $parts[0];

        return $model->getRelation($firstRelation);
    }
}
