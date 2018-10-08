<?php

namespace Nuwave\Lighthouse\Support\DataLoader\Loaders;

use Nuwave\Lighthouse\Execution\QueryFilter;
use Nuwave\Lighthouse\Execution\Utils\Cursor;
use Nuwave\Lighthouse\Execution\Utils\Pagination;
use Nuwave\Lighthouse\Support\DataLoader\BatchLoader;
use Nuwave\Lighthouse\Support\DataLoader\ModelRelationLoader;
use Nuwave\Lighthouse\Schema\Directives\Fields\PaginationManipulator;

class MultipleRelationLoader extends BatchLoader
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
        $parentModels = $this->getParentModels();
        $relations = [$this->relationName => $this->getRelationConstraints()];
        $modelRelationLoader = new ModelRelationLoader($parentModels, $relations);

        switch ($this->paginationType) {
            case PaginationManipulator::PAGINATION_TYPE_CONNECTION:
                // first is an required argument
                $first = $this->resolveArgs['first'];
                $after = Cursor::decode($this->resolveArgs);
                $currentPage = Pagination::calculateCurrentPage($first, $after);

                $modelRelationLoader->loadRelationsForPage($first, $currentPage);
                break;
            case PaginationManipulator::PAGINATION_TYPE_PAGINATOR:
                // count must be set so we can safely get it like this
                $count = $this->resolveArgs['count'];
                $page = array_get($this->resolveArgs, 'page', 1);

                $modelRelationLoader->loadRelationsForPage($count, $page);
                break;
            default:
                $modelRelationLoader->loadRelations();
                break;
        }

        return $modelRelationLoader->getRelationDictionary($this->relationName);
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
