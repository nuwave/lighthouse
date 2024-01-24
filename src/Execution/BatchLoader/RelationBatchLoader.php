<?php declare(strict_types=1);

namespace Nuwave\Lighthouse\Execution\BatchLoader;

use GraphQL\Deferred;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Database\Eloquent\Model;
use Nuwave\Lighthouse\Execution\ModelsLoader\ModelsLoader;
use Nuwave\Lighthouse\Execution\Utils\ModelKey;

class RelationBatchLoader
{
    /**
     * Map from unique model keys to model instances.
     *
     * @var array<string, \Illuminate\Database\Eloquent\Model>
     */
    protected array $parents = [];

    /**
     * Map from unique model keys to the results of batch loading.
     *
     * @var array<string, mixed>
     */
    protected array $results = [];

    /** Marks when the actual batch loading happened. */
    protected bool $hasResolved = false;

    public function __construct(
        protected ModelsLoader $modelsLoader,
    ) {}

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
            static fn (Model $model): string => $model::class,
            true,
        );

        foreach ($parentsGroupedByClass as $parentsOfSameClass) {
            $this->modelsLoader->load($parentsOfSameClass);
        }

        foreach ($parentModels as $model) {
            $modelKey = ModelKey::build($model);
            $this->results[$modelKey] = $this->modelsLoader->extract($model);
        }

        $this->hasResolved = true;
    }
}
