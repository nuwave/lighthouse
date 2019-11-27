<?php

namespace Nuwave\Lighthouse\Pagination;

use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;

class PaginatorField
{
    /**
     * Resolve paginator info for connection.
     *
     * @param  \Illuminate\Pagination\LengthAwarePaginator  $root
     * @return array
     */
    public function paginatorInfoResolver(LengthAwarePaginator $root): array
    {
        return [
            'count' => $root->count(),
            'currentPage' => $root->currentPage(),
            'firstItem' => $root->firstItem(),
            'hasMorePages' => $root->hasMorePages(),
            'lastItem' => $root->lastItem(),
            'lastPage' => $root->lastPage(),
            'perPage' => $root->perPage(),
            'total' => $root->total(),
        ];
    }

    /**
     * Resolve data for connection.
     *
     * @param  \Illuminate\Pagination\LengthAwarePaginator  $root
     * @return \Illuminate\Support\Collection
     */
    public function dataResolver(LengthAwarePaginator $root): Collection
    {
        return $root->values();
    }
}
