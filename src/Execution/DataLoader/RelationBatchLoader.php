<?php

namespace Nuwave\Lighthouse\Execution\DataLoader;

use GraphQL\Deferred;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Database\Eloquent\Model;
use Nuwave\Lighthouse\Execution\Utils\ModelKey;

class RelationBatchLoader
{
    /**
     * A map from unique keys to parent model instances.
     *
     * @var array<string, \Illuminate\Database\Eloquent\Model>
     */
    protected $parents = [];

    /**
     * @var \Nuwave\Lighthouse\Execution\DataLoader\RelationLoader
     */
    protected $relationLoader;

    /**
     * @var string
     */
    protected $relationName;

    /**
     * Marks when the actual batch loading happened.
     *
     * @var bool
     */
    protected $hasResolved = false;

    /**
     * Check if a loader has been registered.
     */
    public function hasRelationLoader(): bool
    {
        return $this->relationLoader !== null;
    }

    /**
     * Register a relation loader.
     *
     * Check hasRelation() before to avoid re-instantiating and re-registering the same loader.
     */
    public function registerRelationLoader(RelationLoader $relationLoader, string $relationName): void
    {
        $this->relationLoader = $relationLoader;
        $this->relationName = $relationName;
    }

    /**
     * Schedule loading a relation off of a concrete parent.
     *
     * This returns effectively a promise that will resolve to
     * the result of loading the relation.
     *
     * As a side-effect, the parent will then hold the relation.
     */
    public function load(Model $parent): Deferred
    {
        $modelKey = ModelKey::build($parent);
        $this->parents[$modelKey] = $parent;

        return new Deferred(function () use ($modelKey) {
            if (! $this->hasResolved) {
                $this->resolve();
            }

            // When we are deep inside a nested query, we can come across the
            // same model in two different paths, so this might be another
            // model instance then $parent.
            $parent = $this->parents[$modelKey];

            return $this->relationLoader->extract($parent, $this->relationName);
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

        foreach ($parentsGroupedByClass as $parentsOfSameClass) {
            $this->relationLoader->load($parentsOfSameClass, $this->relationName);
        }

        $this->hasResolved = true;
    }
}
