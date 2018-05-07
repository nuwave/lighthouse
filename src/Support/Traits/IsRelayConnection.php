<?php

namespace Nuwave\Lighthouse\Support\Traits;



trait IsRelayConnection
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

    /**
     * Paginate connection w/ query args.
     *
     * @param \Illuminate\Database\Eloquent\Builder $query
     * @param array                                 $args
     *
     * @return \Illuminate\Database\Eloquent\Builder
     */
    public function scopePaginatorConnection($query, array $args)
    {
        $first = data_get($args, 'count', 15);
        $page = data_get($args, 'page', 1);

        return $query->paginate($first, ['*'], 'page', $page);
    }

    /**
     * Load connection w/ args.
     *
     * @param \Illuminate\Database\Eloquent\Builder $query
     * @param array                                 $args
     *
     * @return \Illuminate\Database\Eloquent\Builder
     */
    public function scopeLoadConnection($query, array $args)
    {
        $first = isset($args['first']) ? $args['first'] : 15;
        $after = $this->decodeCursor($args);
        $page = isset($args['page']) ? $args['page'] : 1;
        $currentPage = $first && $after ? floor(($first + $after) / $first) : $page;
        $skip = $first * $currentPage;

        return $query->take($first)->skip($skip);
    }
}
