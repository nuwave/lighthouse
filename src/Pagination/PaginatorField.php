<?php

namespace Nuwave\Lighthouse\Pagination;

use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Contracts\Pagination\Paginator;

class PaginatorField
{
    /**
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
     * @return array<mixed>
     */
    public function dataResolver(Paginator $paginator): array
    {
        return $paginator->items();
    }
}
