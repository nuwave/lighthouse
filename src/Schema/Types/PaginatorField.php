<?php

namespace Nuwave\Lighthouse\Schema\Types;

use Illuminate\Support\Collection;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

class PaginatorField
{
    /**
     * Resolve paginator info for connection.
     *
     * @param  \Illuminate\Contracts\Pagination\LengthAwarePaginator  $root
     * @return array
     */
    public function paginatorInfoResolver(LengthAwarePaginator $root): array
    {
        $count = $root->count();
        $currentPage = $root->currentPage();
        $firstItem = $root->firstItem();
        $hasMorePages = $root->hasMorePages();
        $lastItem = $root->lastItem();
        $lastPage = $root->lastPage();
        $perPage = $root->perPage();
        $total = $root->total();

        return compact(
            'count',
            'currentPage',
            'firstItem',
            'hasMorePages',
            'lastItem',
            'lastPage',
            'perPage',
            'total'
        );
    }

    /**
     * Resolve data for connection.
     *
     * @param  \Illuminate\Contracts\Pagination\LengthAwarePaginator  $root
     * @return \Illuminate\Support\Collection
     */
    public function dataResolver(LengthAwarePaginator $root): Collection
    {
        return $root->values();
    }
}
