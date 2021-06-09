<?php

namespace Nuwave\Lighthouse\Execution\BatchLoader;

use GraphQL\Deferred;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Database\Eloquent\Model;
use Nuwave\Lighthouse\Execution\ModelsLoader\ModelsLoader;
use Nuwave\Lighthouse\Execution\Utils\ModelKey;

class RelationBatchLoader
{
    /**
     * @var \Nuwave\Lighthouse\Execution\ModelsLoader\ModelsLoader
     */
    protected $relationLoader;

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

    public function __construct(ModelsLoader $relationLoader)
    {
        $this->relationLoader = $relationLoader;
    }

    /**
     * Schedule loading a relation off of a concrete model.
     *
     * This returns effectively a promise that will resolve to
     * the result of loading the relation.
     *
     * As a side-effect, the model will then hold the relation.
     */
    public function load(Model $model): Deferred
    {
        $modelKey = ModelKey::build($model);
        $this->parents[$modelKey] = $model;

        return new Deferred(function () use ($modelKey) {
            if (! $this->hasResolved) {
                $this->resolve();
            }

            // When we are deep inside a nested query, we can come across the
            // same model in two different paths, so this might be another
            // model instance then $model.
            $parent = $this->parents[$modelKey];

            return $this->relationLoader->extract($parent);
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
            $this->relationLoader->load($parentsOfSameClass);
        }

        $this->hasResolved = true;
    }
}
