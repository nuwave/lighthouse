<?php

namespace Nuwave\Lighthouse\Execution\DataLoader;

use Closure;
use Illuminate\Support\Collection;

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
     * @var \Nuwave\Lighthouse\Pagination\PaginationArgs
     */
    protected $paginationArgs;

    /**
     * @param  string  $relationName
     * @param  \Closure  $decorateBuilder
     * @param  \Nuwave\Lighthouse\Pagination\PaginationArgs  $paginationArgs
     */
    public function __construct(
        string $relationName,
        $decorateBuilder,
        $paginationArgs = null
    ) {
        $this->relationName = $relationName;
        $this->decorateBuilder = $decorateBuilder;
        $this->paginationArgs = $paginationArgs;
    }

    /**
     * Resolve the keys.
     *
     * @return mixed[]
     */
    public function resolve(): array
    {
        $modelRelationFetcher = $this->getRelationFetcher();

        if ($this->paginationArgs !== null) {
            $modelRelationFetcher->loadRelationsForPage($this->paginationArgs);
        } else {
            $modelRelationFetcher->loadRelations();
        }

        return $modelRelationFetcher->getRelationDictionary($this->relationName);
    }

    /**
     * Construct a new instance of a relation fetcher.
     *
     * @return \Nuwave\Lighthouse\Execution\DataLoader\ModelRelationFetcher
     */
    protected function getRelationFetcher(): ModelRelationFetcher
    {
        return new ModelRelationFetcher(
            $this->getParentModels(),
            [$this->relationName => $this->decorateBuilder]
        );
    }

    /**
     * Get the parents from the keys that are present on the BatchLoader.
     *
     * @return \Illuminate\Support\Collection<\Illuminate\Database\Eloquent\Model>
     */
    protected function getParentModels(): Collection
    {
        return (new Collection($this->keys))->pluck('parent');
    }
}
