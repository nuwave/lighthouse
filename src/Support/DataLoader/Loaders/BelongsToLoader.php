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

            $models->fetch([$relation]);
            $models->each(function ($model) use ($relation) {
                $this->set($model->id, $model->getRelation($relation));
            });
        });
    }
}
