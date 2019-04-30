<?php

namespace Nuwave\Lighthouse\Pagination;

use Illuminate\Support\Collection;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

class ConnectionField
{
    /**
     * Resolve page info for connection.
     *
     * @param  \Illuminate\Contracts\Pagination\LengthAwarePaginator  $paginator
     * @return array
     */
    public function pageInfoResolver(LengthAwarePaginator $paginator): array
    {
        return [
            'total' => $paginator->total(),
            'count' => $paginator->count(),
            'currentPage' => $paginator->currentPage(),
            'lastPage' => $paginator->lastPage(),
            'hasNextPage' => $paginator->hasMorePages(),
            'hasPreviousPage' => $paginator->currentPage() > 1,
            'startCursor' => $paginator->firstItem()
                ? Cursor::encode($paginator->firstItem())
                : null,
            'endCursor' => $paginator->lastItem()
                ? Cursor::encode($paginator->lastItem())
                : null,
        ];
    }

    /**
     * Resolve edges for connection.
     *
     * @param  \Illuminate\Contracts\Pagination\LengthAwarePaginator  $paginator
     * @return \Illuminate\Support\Collection
     */
    public function edgeResolver(LengthAwarePaginator $paginator): Collection
    {
        $firstItem = $paginator->firstItem();

        return $paginator->values()->map(function ($item, $index) use ($firstItem) {
            return [
                'cursor' => Cursor::encode($firstItem + $index),
                'node' => $item,
            ];
        });
    }
}
