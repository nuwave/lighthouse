<?php

namespace Nuwave\Lighthouse\Execution\BatchLoader;

use GraphQL\Deferred;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Database\Eloquent\Model;
use Nuwave\Lighthouse\Execution\ModelsLoader\ModelsLoader;

/**
 * Simplified version of @see RelationBatchLoader that does not keep track of results.
 */
class SideEffectingRelationBatchLoader
{
    /**
     * @var \Nuwave\Lighthouse\Execution\ModelsLoader\ModelsLoader
     */
    protected $modelsLoader;

    /**
     * @var \Illuminate\Database\Eloquent\Collection<\Illuminate\Database\Eloquent\Model>
     */
    protected $parents;

    /**
     * Marks when the actual batch loading happened.
     *
     * @var bool
     */
    protected $hasResolved = false;

    public function __construct(ModelsLoader $modelsLoader)
    {
        $this->modelsLoader = $modelsLoader;
        $this->parents = new EloquentCollection();
    }

    /**
     * Schedule loading a relation off of a concrete model.
     *
     * As a side effect, the model will then hold the relation.
     */
    public function load(Model $model): Deferred
    {
        $this->parents->push($model);

        return new Deferred(function (): void {
            if (! $this->hasResolved) {
                $this->resolve();
            }
        });
    }

    protected function resolve(): void
    {
        // Monomorphize the models to simplify eager loading relations onto them
        $parentsGroupedByClass = $this->parents->groupBy(
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

        $this->hasResolved = true;
    }
}
