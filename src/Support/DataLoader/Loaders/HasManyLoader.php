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
        $this->keys->map(function ($item) {
            // Dataloaders can be nested in different levels of the query,
            // and therefore can be called with different arguments. Here
            // we are grouping them by their arguments and then resolving to
            // ensure we query the data correctly.
            return array_merge($item, ['json' => json_encode($item['args'])]);
        })->groupBy('json')->each(function ($items) {
            $relation = data_get($items->first(), 'relation');

            $items->pluck('root')->fetch(['relation' => function ($q) use ($items) {
                // Lighthouse ships with a collection helper called "fetch". This
                // allows us to lazy eager load data on a collection. However,
                // unlike the built in "load" function, this will eager load AND
                // allow limitations (such as "take" or "skip").
                $q->loadConnection(array_get($items->first(), 'args', []));
            }])->each(function ($user) {
                // Finally, we need to set the value for the provided key. When
                // the GraphQL query is executed, it will grab the value from
                // for the key when resolving the field.
                $this->set($user->id, $user->tasks);
            });
        });
    }
}
