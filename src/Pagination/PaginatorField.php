<?php

namespace Nuwave\Lighthouse\Pagination;

use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Pagination\AbstractPaginator;

class PaginatorField
{
    /**
     * Resolve paginator info for connection.
     *
     * @return array<string, mixed>
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
     * @return array<mixed>
     */
    public function dataResolver(AbstractPaginator $root): array
    {
        return $root->items();
    }
}
