<?php

namespace Nuwave\Lighthouse\Execution\DataLoader;

use GraphQL\Deferred;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Database\Eloquent\Model;
use Nuwave\Lighthouse\Execution\Utils\ModelKey;

class RelationBatchLoader
{
    /**
     * A map from relation names to responsible fetcher instances.
     *
     * @var array<string, \Nuwave\Lighthouse\Execution\DataLoader\RelationFetcher>
     */
    protected $relations = [];

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

    public function hasRelation(string $relationName): bool
    {
        return isset($this->relations[$relationName]);
    }

    public function registerRelation(string $relationName, RelationFetcher $relationFetcher): void
    {
        $this->relations[$relationName] = $relationFetcher;
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

        foreach ($this->relations as $relation => $relationFetcher) {
            foreach ($parentsGroupedByClass as $parentsOfSameClass) {
                $relationFetcher->fetch($parentsOfSameClass, $relation);
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
