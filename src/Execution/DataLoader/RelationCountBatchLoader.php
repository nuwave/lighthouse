<?php

namespace Nuwave\Lighthouse\Execution\DataLoader;

use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Database\Eloquent\Model;
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
     *
     * @return \Illuminate\Support\Collection<string, \Illuminate\Database\Eloquent\Model>
     */
    protected function getParentModels(): Collection
    {
        return (new Collection($this->keys))
            // Models are grouped by their fully qualified class name to prevent key
            // collisions between different types of models.
            ->groupBy(
                /**
                 * @param  array<string, mixed>  $key
                 * @return class-string<\Illuminate\Database\Eloquent\Model>
                 */
                static function (array $key): string {
                    return get_class($key['parent']);
                },
                true
            )
            ->mapWithKeys(
                /**
                 * @param  \Illuminate\Support\Collection<array>  $keys
                 */
                function (Collection $keys): EloquentCollection {
                    $parents = $keys->map(
                        /**
                         * @param  array<string, mixed>  $meta
                         */
                        static function (array $meta): Model {
                            return $meta['parent'];
                        }
                    );

                    return (new EloquentCollection($parents))
                        ->loadCount([$this->relationName => $this->decorateBuilder]);
                }
            );
    }
}
