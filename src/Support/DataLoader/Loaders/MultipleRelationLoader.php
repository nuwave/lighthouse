<?php

namespace Nuwave\Lighthouse\Support\DataLoader\Loaders;

use Nuwave\Lighthouse\Execution\QueryFilter;
use Nuwave\Lighthouse\Execution\Utils\Cursor;
use Nuwave\Lighthouse\Execution\Utils\Pagination;
use Nuwave\Lighthouse\Support\DataLoader\BatchLoader;
use Nuwave\Lighthouse\Schema\Directives\Fields\PaginationManipulator;
use Nuwave\Lighthouse\Support\DataLoader\ModelRelationLoader;

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
     * @var string
     */
    protected $paginationType;

    /**
     * @param string $relationName
     * @param array $resolveArgs
     * @param array $scopes
     * @param string $paginationType
     */
    public function __construct(string $relationName, array $resolveArgs, array $scopes, string $paginationType)
    {
        $this->relationName = $relationName;
        $this->resolveArgs = $resolveArgs;
        $this->scopes = $scopes;
        $this->paginationType = $paginationType;
    }

    /**
     * @return array
     * @throws \Exception
     */
    public function resolve(): array
    {
        $parentModels = $this->getParentModels();
        $relations = [$this->relationName => $this->getRelationConstraints()];
        $modelRelationLoader = new ModelRelationLoader($parentModels, $relations);

        switch ($this->paginationType) {
            case PaginationManipulator::PAGINATION_TYPE_CONNECTION:
            case PaginationManipulator::PAGINATION_ALIAS_RELAY:
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
     *
     * @return \Closure
     */
    protected function getRelationConstraints(): \Closure
    {
        return function ($query) {
            foreach ($this->scopes as $scope) {
                $query->$scope();
            }

            $query->when(isset($args[QueryFilter::QUERY_FILTER_KEY]), function ($query) {
                return QueryFilter::build($query, $this->resolveArgs);
            });
        };
    }
}
