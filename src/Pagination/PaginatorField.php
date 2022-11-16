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
    public function paginatorInfoResolver(LengthAwarePaginator $paginator): array
    {
        return [
            'count' => count($paginator->items()),
            'currentPage' => $paginator->currentPage(),
            'firstItem' => $paginator->firstItem(),
            'hasMorePages' => $paginator->hasMorePages(),
            'lastItem' => $paginator->lastItem(),
            'lastPage' => $paginator->lastPage(),
            'perPage' => $paginator->perPage(),
            'total' => $paginator->total(),
        ];
    }

    /**
     * Resolve data for connection.
     *
     * @return array<mixed>
     */
    public function dataResolver(AbstractPaginator $paginator): array
    {
        return $paginator->items();
    }
}
