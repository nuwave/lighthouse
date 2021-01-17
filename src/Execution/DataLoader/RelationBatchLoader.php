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
     * @var array<string, \Nuwave\Lighthouse\Execution\DataLoader\RelationLoader>
     */
    protected $relationLoaders = [];

    /**
     * A map from unique keys to parent model instances.
     *
     * @var array<string, \Illuminate\Database\Eloquent\Model>
     */
    protected $parents = [];

    /**
     * Marks when the actual batch loading happened.
     *
     * @var bool
     */
    protected $hasResolved = false;

    /**
     * Check if a loader has been registered for the given relation name.
     */
    public function hasRelationLoader(string $relationName): bool
    {
        return isset($this->relationLoaders[$relationName]);
    }

    /**
     * Register a relation loader for a given relation name.
     *
     * Check hasRelation() before to avoid re-instantiating and re-registering the same loader.
     */
    public function registerRelationLoader(string $relationName, RelationLoader $relationLoader): void
    {
        $this->relationLoaders[$relationName] = $relationLoader;
    }

    /**
     * Schedule loading a relation off of a concrete parent.
     *
     * This returns effectively a promise that will resolve to
     * the result of loading the relation.
     *
     * As a side-effect, the parent will then hold the relation.
     */
    public function load(string $relationName, Model $parent): Deferred
    {
        $modelKey = ModelKey::build($parent);
        $this->parents[$modelKey] = $parent;

        return new Deferred(function () use ($parent, $relationName) {
            if (! $this->hasResolved) {
                $this->resolve();
            }

            $relationFetcher = $this->relationLoaders[$relationName];

            return $relationFetcher->extract($parent, $relationName);
        });
    }

    public function resolve(): void
    {
        $parentModels = new EloquentCollection($this->parents);

        // Monomorphize the models to simplify eager loading relations onto them
        $parentsGroupedByClass = $parentModels->groupBy(
            /**
             * @return class-string<\Illuminate\Database\Eloquent\Model>
             */
            static function (Model $model): string {
                return get_class($model);
            },
            true
        );

        foreach ($this->relationLoaders as $relation => $relationLoader) {
            foreach ($parentsGroupedByClass as $parentsOfSameClass) {
                $relationLoader->load($parentsOfSameClass, $relation);
            }
        }

        $this->hasResolved = true;
    }
}
