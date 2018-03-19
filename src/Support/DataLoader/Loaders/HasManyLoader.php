<?php

namespace Nuwave\Lighthouse\Support\DataLoader\Loaders;

use Nuwave\Lighthouse\Support\DataLoader\BatchLoader;

class HasManyLoader extends BatchLoader
{
    /**
     * Resolve keys.
     */
    public function resolve()
    {
        collect($this->keys)->map(function ($item) {
            return array_merge($item, ['json' => json_encode($item['args'])]);
        })->groupBy('json')->each(function ($items) {
            $first = $items->first();
            $relation = $first['relation'];
            $parents = $items->pluck('parent');

            $parents->fetch([$relation => function ($q) use ($first) {
                $args = $first['args'];
                $type = $first['type'];
                $scopes = array_get($first, 'scopes', []);

                foreach ($scopes as $scope) {
                    call_user_func_array([$q, $scope], [$args]);
                }

                switch ($type) {
                    case 'relay':
                        return $q->relayConnection($args);
                    case 'paginator':
                        return $q->paginatorConnection($args);
                    default:
                        return $q;
                }
            }]);

            $parents->each(function ($model) use ($relation) {
                $this->set($model->id, $model->getRelation($relation));
            });
        });
    }
}
