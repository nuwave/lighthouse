<?php

namespace Nuwave\Lighthouse\Execution\DataLoader;

use Illuminate\Support\Collection;
use Nuwave\Lighthouse\Execution\QueryFilter;
use Nuwave\Lighthouse\Execution\Utils\Cursor;
use Nuwave\Lighthouse\Execution\Utils\Pagination;
use Nuwave\Lighthouse\Schema\Directives\Fields\PaginationManipulator;

class RelationBatchLoader extends BatchLoader
{
    /**
     * @var string
     */
    protected $relationName;
    /**
     * @var array
     */
    protected $resolveArgs;
    /**
     * @var array
     */
    protected $scopes;
    /**
     * The pagination type can either be "connection", "paginator" or null, in which case there is no pagination.
     *
     * @var string|null
     */
    protected $paginationType;
    
    /**
     * @param string $relationName
     * @param array $resolveArgs
     * @param array $scopes
     * @param string|null $paginationType
     */
    public function __construct(string $relationName, array $resolveArgs, array $scopes, string $paginationType = null)
    {
        $this->relationName = $relationName;
        $this->resolveArgs = $resolveArgs;
        $this->scopes = $scopes;
        $this->paginationType = $paginationType;
    }
    
    /**
     * Resolve the keys.
     *
     * @throws \Exception
     *
     * @return array
     */
    public function resolve(): array
    {
        $modelRelationFetcher = $this->getRelationFetcher();
    
        switch ($this->paginationType) {
            case PaginationManipulator::PAGINATION_TYPE_CONNECTION:
                // first is an required argument
                $first = $this->resolveArgs['first'];
                $after = Cursor::decode($this->resolveArgs);
                $currentPage = Pagination::calculateCurrentPage($first, $after);
            
                $modelRelationFetcher->loadRelationsForPage($first, $currentPage);
                break;
            case PaginationManipulator::PAGINATION_TYPE_PAGINATOR:
                // count must be set so we can safely get it like this
                $count = $this->resolveArgs['count'];
                $page = array_get($this->resolveArgs, 'page', 1);
            
                $modelRelationFetcher->loadRelationsForPage($count, $page);
                break;
            default:
                $modelRelationFetcher->loadRelations();
                break;
        }
    
        return $modelRelationFetcher->getRelationDictionary($this->relationName);
    }
    
    /**
     * @return ModelRelationFetcher
     */
    protected function getRelationFetcher(): ModelRelationFetcher
    {
        return new ModelRelationFetcher(
            $this->getParentModels(),
            [$this->relationName => $this->getRelationConstraints()]
        );
    }
    
    /**
     * Get the parents from the keys that are present on the BatchLoader.
     *
     * @return Collection
     */
    protected function getParentModels(): Collection
    {
        return collect($this->keys)->pluck('parent');
    }
    
    /**
     * Returns a closure that adds the scopes and the filters to the query.
     *
     * @return \Closure
     */
    protected function getRelationConstraints(): \Closure
    {
        return function ($query) {
            foreach ($this->scopes as $scope) {
                $query->$scope();
            }
            
            $query->when(
                isset($args[QueryFilter::QUERY_FILTER_KEY]),
                function ($query) {
                    return QueryFilter::build($query, $this->resolveArgs);
                }
            );
        };
    }
}
