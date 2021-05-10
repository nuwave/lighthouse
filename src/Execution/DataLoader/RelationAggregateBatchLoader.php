<?php

namespace Nuwave\Lighthouse\Execution\DataLoader;

use GraphQL\Deferred;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Database\Eloquent\Model;
use Nuwave\Lighthouse\Execution\Utils\ModelKey;

class RelationAggregateBatchLoader
{
    /**
     * A map from unique keys to parent model instances.
     *
     * @var array<string, \Illuminate\Database\Eloquent\Model>
     */
    protected $parents = [];

    /**
     * @var \Nuwave\Lighthouse\Execution\DataLoader\RelationAggregateLoader
     */
    protected $relationAggregateLoader;

    /**
     * @var string
     */
    protected $relationName;

    /**
     * @var string
     */
    protected $relationColumn;

    /**
     * Marks when the actual batch loading happened.
     *
     * @var bool
     */
    protected $hasResolved = false;

    /**
     * Check if a loader has been registered.
     */
    public function hasRelationAggregateLoader(): bool
    {
        return $this->relationAggregateLoader !== null;
    }

    /**
     *
     * Check hasRelation() before to avoid re-instantiating and re-registering the same loader.
     */
    public function registerRelationAggregateLoader(RelationAggregateLoader $relationAggregateLoader, string $relationName, string $relationColumn): void
    {
        $this->relationAggregateLoader = $relationAggregateLoader;
        $this->relationName = $relationName;
        $this->relationColumn = $relationColumn;
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

            return $this->relationAggregateLoader->extract($parent, $this->relationName, $this->relationColumn);
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
            $this->relationAggregateLoader->load($parentsOfSameClass, $this->relationName, $this->relationColumn);
        }

        $this->hasResolved = true;
    }
}
