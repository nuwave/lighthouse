<?php

namespace Nuwave\Lighthouse\Support\Traits;

use Nuwave\Lighthouse\Support\Traits\HandlesGlobalId;

trait HasRelayConnections
{
    use HandlesGlobalId;

    /**
     * Paginate connection w/ query args.
     *
     * @param \Illuminate\Database\Eloquent\Builder $query
     * @param array                                 $args
     *
     * @return \Illuminate\Database\Eloquent\Builder
     */
    public function scopeRelayConnection($query, array $args)
    {
        $first = data_get($args, 'first', 15);
        $page = data_get($args, 'page', 1);
        $after = $this->decodeCursor($args);
        $currentPage = $first && $after ? floor(($first + $after) / $first) : $page;

        return $query->paginate($first, ['*'], 'page', $currentPage);
    }
}
