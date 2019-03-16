<?php

namespace Nuwave\Lighthouse\Execution\DataLoader;

use Illuminate\Support\Arr;
use Illuminate\Support\Collection;
use GraphQL\Type\Definition\ResolveInfo;
use Nuwave\Lighthouse\Execution\QueryFilter;
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
     * @var mixed[]
     */
    protected $args;

    /**
     * Names of the scopes that have to be called for the query.
     *
     * @var string[]
     */
    protected $scopes;

    /**
     * The ResolveInfo of the currently executing field. Used for retrieving
     * the QueryFilter.
     *
     * @var \GraphQL\Type\Definition\ResolveInfo
     */
    protected $resolveInfo;

    /**
     * The pagination type can either be "connection", "paginator" or null, in which case there is no pagination.
     *
     * @var string|null
     */
    protected $paginationType;

    /**
     * The paginator can be limited to only allow querying a maximum number of items.
     *
     * @var int|null
     */
    protected $paginateMaxCount;

    /**
     * @param  string  $relationName
     * @param  mixed[]  $args
     * @param  string[]  $scopes
     * @param  \GraphQL\Type\Definition\ResolveInfo  $resolveInfo
     * @param  string|null  $paginationType
     * @param  int|null  $paginateMaxCount
     * @return void
     */
    public function __construct(
        string $relationName,
        array $args,
        array $scopes,
        ResolveInfo $resolveInfo,
        ?string $paginationType = null,
        ?int $paginateMaxCount = null
    ) {
        $this->relationName = $relationName;
        $this->args = $args;
        $this->scopes = $scopes;
        $this->resolveInfo = $resolveInfo;
        $this->paginationType = $paginationType;
        $this->paginateMaxCount = $paginateMaxCount;
    }

    /**
     * Resolve the keys.
     *
     * @return mixed[]
     */
    public function resolve(): array
    {
        $modelRelationFetcher = $this->getRelationFetcher();

        switch ($this->paginationType) {
            case PaginationManipulator::PAGINATION_TYPE_CONNECTION:
                // first is an required argument
                /** @var int $first */
                $first = $this->args['first'];
                Pagination::throwIfPaginateMaxCountExceeded($this->paginateMaxCount, $first);

                $after = Cursor::decode($this->args);

                $currentPage = Pagination::calculateCurrentPage($first, $after);

                $modelRelationFetcher->loadRelationsForPage($first, $currentPage);
                break;
            case PaginationManipulator::PAGINATION_TYPE_PAGINATOR:
                // count must be set so we can safely get it like this
                /** @var int $count */
                $count = $this->args['count'];
                Pagination::throwIfPaginateMaxCountExceeded($this->paginateMaxCount, $count);

                $page = Arr::get($this->args, 'page', 1);

                $modelRelationFetcher->loadRelationsForPage($count, $page);
                break;
            default:
                $modelRelationFetcher->loadRelations();
                break;
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
            [$this->relationName => function ($query) {
                return QueryFilter::apply(
                    $query,
                    $this->args,
                    $this->scopes,
                    $this->resolveInfo
                );
            }]
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
