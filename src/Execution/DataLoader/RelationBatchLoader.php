<?php

namespace Nuwave\Lighthouse\Execution\DataLoader;

use Illuminate\Support\Collection;
use Nuwave\Lighthouse\Execution\QueryUtils;
use Nuwave\Lighthouse\Execution\Utils\Cursor;
use Nuwave\Lighthouse\Execution\Utils\Pagination;
use Nuwave\Lighthouse\Schema\Directives\Fields\PaginationManipulator;

class RelationBatchLoader extends BatchLoader
{
    /**
     * The name of the Eloquent relation to load.
     *
     * @var string
     */
    protected $relationName;
    /**
     * The arguments that were passed to the field.
     *
     * @var array
     */
    protected $args;
    /**
     * Names of the scopes that have to be called for the query.
     *
     * @var string[]
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
     * @param array $args
     * @param array $scopes
     * @param string|null $paginationType
     */
    public function __construct(string $relationName, array $args, array $scopes, string $paginationType = null)
    {
        $this->relationName = $relationName;
        $this->args = $args;
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
                $first = $this->args['first'];
                $after = Cursor::decode($this->args);
                $currentPage = Pagination::calculateCurrentPage($first, $after);
            
                $modelRelationFetcher->loadRelationsForPage($first, $currentPage);
                break;
            case PaginationManipulator::PAGINATION_TYPE_PAGINATOR:
                // count must be set so we can safely get it like this
                $count = $this->args['count'];
                $page = array_get($this->args, 'page', 1);
            
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
            [$this->relationName => function ($query) {
                $query = QueryUtils::applyScopes($query, $this->args, $this->scopes);
                return QueryUtils::applyFilters($query, $this->args);
            }]
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
}
