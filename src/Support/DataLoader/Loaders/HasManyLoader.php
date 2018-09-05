<?php

namespace Nuwave\Lighthouse\Support\DataLoader\Loaders;

use Illuminate\Support\Collection;
use Illuminate\Database\Eloquent\Model;
use Nuwave\Lighthouse\Support\Database\QueryFilter;
use Nuwave\Lighthouse\Support\DataLoader\BatchLoader;
use Nuwave\Lighthouse\Support\Traits\HandlesGlobalId;
use Nuwave\Lighthouse\Schema\Directives\Fields\PaginationManipulator;

class HasManyLoader extends BatchLoader
{
    use HandlesGlobalId;

    /**
     * @var string
     */
    protected $relation;
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
     * @param string $relation
     * @param array $resolveArgs
     * @param array $scopes
     * @param string $paginationType
     */
    public function __construct(string $relation, array $resolveArgs, array $scopes, string $paginationType)
    {
        $this->relation = $relation;
        $this->resolveArgs = $resolveArgs;
        $this->scopes = $scopes;
        $this->paginationType = $paginationType;
    }

    /**
     * {@inheritdoc}
     */
    public function resolve(): array
    {
        $eagerLoadRelationWithConstraints = [$this->relation => function ($query) {
            foreach ($this->scopes as $scope) {
                call_user_func_array([$query, $scope], [$this->resolveArgs]);
            }

            $query->when(isset($args['query.filter']), function ($q) {
                return QueryFilter::build($q, $this->resolveArgs);
            });
        }];

        /** @var Collection $parents */
        $parents = collect($this->keys)->pluck('parent');
        switch ($this->paginationType) {
            case PaginationManipulator::PAGINATION_TYPE_CONNECTION:
            case PaginationManipulator::PAGINATION_ALIAS_RELAY:
                $first = data_get($this->resolveArgs, 'first', 15);
                $after = $this->decodeCursor($this->resolveArgs);
                $currentPage = $first && $after ? floor(($first + $after) / $first) : 1;
                $parents->fetchForPage($first, $currentPage, $eagerLoadRelationWithConstraints);
                break;
            case PaginationManipulator::PAGINATION_TYPE_PAGINATOR:
                // count must be set
                $count = $this->resolveArgs['count'];
                $page = data_get($this->resolveArgs, 'page', 1);
                $parents->fetchForPage($count, $page, $eagerLoadRelationWithConstraints);
                break;
            default:
                $parents->fetch($eagerLoadRelationWithConstraints);
                break;
        }

        return $parents->mapWithKeys(function (Model $model) {
            return [$model->getKey() => $model->getRelation($this->relation)];
        })->all();
    }
}
