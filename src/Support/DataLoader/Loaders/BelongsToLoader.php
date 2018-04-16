<?php

namespace Nuwave\Lighthouse\Support\DataLoader\Loaders;

use Nuwave\Lighthouse\Support\DataLoader\BatchLoader;

class BelongsToLoader extends BatchLoader
{
    /**
     * Resolve keys.
     */
    public function resolve()
    {
        collect($this->keys)->map(function ($item) {
            return array_merge($item, ['json' => json_encode($item['args'])]);
        })->groupBy('json')->each(function ($items) {
            $relation = array_get($items->first(), 'relation');
            $models = $items->pluck('root');
            $args = array_get($items->first(), 'args', []);

            $models->fetch([$relation => function ($q) use ($args) {
                $q->when(isset($args['query.filter']), function ($q) use ($args) {
                    return QueryFilter::build($q, $args);
                });
            }]);

            $models->each(function ($model) use ($relation) {
                $this->set($model->id, $model->getRelation($relation));
            });
        });
    }
}
