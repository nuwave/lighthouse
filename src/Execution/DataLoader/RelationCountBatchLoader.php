<?php

namespace Nuwave\Lighthouse\Execution\DataLoader;

use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Support\Collection;

class RelationCountBatchLoader extends BatchLoader
{
    /**
     * The name of the Eloquent relation to count.
     *
     * @var string
     */
    protected $relationName;

    /**
     * This function is called with the relation query builder and may modify it.
     *
     * @var \Closure
     */
    protected $decorateBuilder;

    /**
     * @param  \Closure  $decorateBuilder
     */
    public function __construct(
        string $relationName,
        // Not using a type-hint to avoid resolving those params through the container
        $decorateBuilder
    ) {
        $this->relationName = $relationName;
        $this->decorateBuilder = $decorateBuilder;
    }

    /**
     * Eager-load the relation count.
     *
     * @return array<\Illuminate\Database\Eloquent\Model>
     */
    public function resolve(): array
    {
        return $this->getParentModels()->all();
    }

    /**
     * Get the parent models from the keys that are present on the BatchLoader.
     * Models are grouped by their fully qualified class name to prevent key
     * collisions between different types of models.
     *
     * @return Collection<Model>
     */
    protected function getParentModels(): Collection
    {
        return Collection::make($this->keys)
            ->groupBy(function (array $key) {
                return get_class($key['parent']);
            }, true)
            ->mapWithKeys(function (Collection $keys) {
                $parentKeys = $keys->map(function (array $meta) {
                    return $meta['parent'];
                });

                return (new EloquentCollection($parentKeys))
                    ->loadCount([$this->relationName => $this->decorateBuilder]);
            });
    }
}
