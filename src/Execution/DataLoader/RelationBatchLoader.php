<?php

namespace Nuwave\Lighthouse\Execution\DataLoader;

use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;
use Nuwave\Lighthouse\Execution\Utils\ModelKey;

class RelationBatchLoader extends BatchLoader
{
    /**
     * The name of the Eloquent relation to load.
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
     * Optionally, a relation may be paginated.
     *
     * @var \Nuwave\Lighthouse\Pagination\PaginationArgs|null
     */
    protected $paginationArgs;

    /**
     * @param  \Closure  $decorateBuilder
     * @param  \Nuwave\Lighthouse\Pagination\PaginationArgs  $paginationArgs
     */
    public function __construct(
        string $relationName,
        // Not using a type-hint to avoid resolving those params through the container
        $decorateBuilder,
        $paginationArgs = null
    ) {
        $this->relationName = $relationName;
        $this->decorateBuilder = $decorateBuilder;
        $this->paginationArgs = $paginationArgs;
    }

    /**
     * Eager-load the relation.
     *
     * @return array<string, mixed>
     */
    public function resolve(): array
    {
        $relation = [$this->relationName => $this->decorateBuilder];

        if ($this->paginationArgs !== null) {
            $modelRelationFetcher = new ModelRelationFetcher(
                $this->getParentModels(false),
                $relation
            );
            $models = $modelRelationFetcher->loadRelationsForPage($this->paginationArgs);
        } else {
            $models = $this->getParentModels();
        }

        return $models
            ->mapWithKeys(
                /**
                 * @return array<string, mixed>
                 */
                function (Model $model): array {
                    return [ModelKey::build($model) => $this->extractRelation($model)];
                }
            )
            ->all();
    }

    /**
     * Get the parents from the keys that are present on the BatchLoader.
     *
     * @return EloquentCollection<mixed>
     */
    protected function getParentModels(bool $shouldLoadRelation = true): EloquentCollection
    {
        return (new EloquentCollection($this->keys))
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
                function (Collection $keys) use ($shouldLoadRelation) {
                    $parents = $keys->map(
                        /**
                         * @param  array<string, mixed>  $meta
                         */
                        static function (array $meta): Model {
                            return $meta['parent'];
                        }
                    );

                    return (new EloquentCollection($parents))
                        ->when(
                            $shouldLoadRelation,
                            /**
                             * @param Illuminate\Database\Eloquent\Collection<mixed> $parents
                             */
                            function (EloquentCollection $parents) {
                                return $parents->load([
                                    $this->relationName => $this->decorateBuilder,
                                ]);
                            }
                        );
                }
            );
    }

    /**
     * Extract the relation that was loaded.
     *
     * @return mixed The model's relation.
     */
    protected function extractRelation(Model $model)
    {
        // Dot notation may be used to eager load nested relations
        $parts = explode('.', $this->relationName);

        // We just return the first level of relations for now. They
        // hold the nested relations in case they are needed.
        $firstRelation = $parts[0];

        return $model->getRelation($firstRelation);
    }
}
