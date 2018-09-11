<?php

namespace Nuwave\Lighthouse\Support\Traits;

use Nuwave\Lighthouse\Execution\Utils\Cursor;
use Nuwave\Lighthouse\Execution\Utils\Pagination;

trait IsRelayConnection
{
    /**
     * Paginate connection w/ query args.
     *
     * @param \Illuminate\Database\Eloquent\Builder $query
     * @param array $args
     *
     * @return \Illuminate\Contracts\Pagination\LengthAwarePaginator
     */
    public function scopeRelayConnection($query, array $args)
    {
        $first = array_get($args, 'first', 15);
        $page = array_get($args, 'page', 1);
        $after = Cursor::decode($args);

        $currentPage = Pagination::calculateCurrentPage($first, $after, $page);

        return $query->paginate($first, ['*'], 'page', $currentPage);
    }

    /**
     * Paginate connection w/ query args.
     *
     * @param \Illuminate\Database\Eloquent\Builder $query
     * @param array $args
     *
     * @return \Illuminate\Contracts\Pagination\LengthAwarePaginator
     */
    public function scopePaginatorConnection($query, array $args)
    {
        $first = array_get($args, 'count', 15);
        $page = array_get($args, 'page', 1);

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
        $first = array_get($args, 'first', 15);
        $after = Cursor::decode($args);
        $page = array_get($args, 'page', 1);

        $currentPage = Pagination::calculateCurrentPage($first, $after, $page);
        $skip = $first * $currentPage;

        return $query->take($first)->skip($skip);
    }
}
