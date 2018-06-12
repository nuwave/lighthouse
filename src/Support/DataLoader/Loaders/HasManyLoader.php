<?php

namespace Nuwave\Lighthouse\Support\DataLoader\Loaders;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;
use Nuwave\Lighthouse\Schema\Directives\PaginatorCreatingDirective;
use Nuwave\Lighthouse\Support\Database\QueryFilter;
use Nuwave\Lighthouse\Support\DataLoader\BatchLoader;
use Nuwave\Lighthouse\Support\Traits\HandlesGlobalId;

class HasManyLoader extends BatchLoader
{
    use HandlesGlobalId;

    /**
     * Resolve keys.
     */
    public function resolve()
    {
        collect($this->keys)->map(function ($item) {
            return array_merge($item, ['json' => json_encode($item['args'])]);
        })->groupBy('json')->each(function (Collection $items) {
            $first = $items->first();
            $parents = $items->pluck('parent');
            $scopes = array_get($first, 'scopes', []);
            $relation = $first['relation'];
            $resolverType = $first['type'];
            $args = $first['args'];

            $constraints = [$relation => function ($query) use ($scopes, $args) {
                foreach ($scopes as $scope) {
                    call_user_func_array([$query, $scope], [$args]);
                }

                $query->when(isset($args['query.filter']), function ($q) use ($args) {
                    return QueryFilter::build($q, $args);
                });
            }];

            switch ($resolverType) {
                case PaginatorCreatingDirective::PAGINATION_TYPE_PAGINATOR:
                    $first = data_get($args, 'count', 15);
                    $page = data_get($args, 'page', 1);
                    $parents->fetchForPage($first, $page, $constraints);
                    break;
                case PaginatorCreatingDirective::PAGINATION_TYPE_CONNECTION:
                    $first = data_get($args, 'first', 15);
                    $after = $this->decodeCursor($args);
                    $currentPage = $first && $after ? floor(($first + $after) / $first) : 1;
                    $parents->fetchForPage($first, $currentPage, $constraints);
                    break;
                default:
                    $parents->fetch($constraints);
                    break;
            }

            $parents->each(function (Model $model) use ($relation) {
                $this->set($model->getKey(), $model->getRelation($relation));
            });
        });
    }
}
