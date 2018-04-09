<?php

namespace Nuwave\Lighthouse\Support\DataLoader\Loaders;

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
        })->groupBy('json')->each(function ($items) {
            $first = $items->first();
            $parents = $items->pluck('parent');
            $scopes = array_get($first, 'scopes', []);
            $relation = $first['relation'];
            $type = $first['type'];
            $args = $first['args'];

            $constraints = [$relation => function ($q) use ($scopes, $args) {
                foreach ($scopes as $scope) {
                    call_user_func_array([$q, $scope], [$args]);
                }
            }];

            switch ($type) {
                case 'relay':
                    $first = data_get($args, 'first', 15);
                    $after = $this->decodeCursor($args);
                    $currentPage = $first && $after ? floor(($first + $after) / $first) : 1;
                    $parents->fetchForPage($first, $currentPage, $constraints);
                    break;
                case 'paginator':
                    $first = data_get($args, 'count', 15);
                    $page = data_get($args, 'page', 1);
                    $parents->fetchForPage($first, $page, $constraints);
                    break;
                default:
                    $parents->fetch($constraints);
                    break;
            }

            $parents->each(function ($model) use ($relation) {
                $this->set($model->id, $model->getRelation($relation));
            });
        });
    }
}
