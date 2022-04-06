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
    protected $modelsLoader;

    /**
     * Map from unique model keys to model instances.
     *
     * @var array<string, \Illuminate\Database\Eloquent\Model>
     */
    protected $parents = [];

    /**
     * Map from unique model keys to the results of batch loading.
     *
     * @var array<string, mixed>
     */
    protected $results = [];

    /**
     * Marks when the actual batch loading happened.
     *
     * @var bool
     */
    protected $hasResolved = false;

    public function __construct(ModelsLoader $modelsLoader)
    {
        $this->modelsLoader = $modelsLoader;
    }

    /**
     * Schedule loading a relation off of a concrete model.
     *
     * This returns effectively a promise that will resolve to
     * the result of loading the relation.
     *
     * As a side effect, the model will then hold the relation.
     */
    public function load(Model $model): Deferred
    {
        $modelKey = ModelKey::build($model);
        $this->parents[$modelKey] = $model;

        return new Deferred(function () use ($modelKey) {
            if (! $this->hasResolved) {
                $this->resolve();
            }

            return $this->results[$modelKey];
        });
    }

    protected function resolve(): void
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
            // TODO remove when we update to Laravel 9 which has correct stubs
            // @phpstan-ignore-next-line Parameter #1 $parents of method Nuwave\Lighthouse\Execution\ModelsLoader\ModelsLoader::load() expects Illuminate\Database\Eloquent\Collection, Illuminate\Support\Collection<(int|string), mixed> given.
            $this->modelsLoader->load($parentsOfSameClass);
        }

        foreach ($parentModels as $model) {
            $modelKey = ModelKey::build($model);
            $this->results[$modelKey] = $this->modelsLoader->extract($model);
        }

        $this->hasResolved = true;
    }
}
