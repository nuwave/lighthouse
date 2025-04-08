<?php declare(strict_types=1);

namespace Nuwave\Lighthouse\Pagination;

use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Contracts\Pagination\Paginator;

class PaginatorField
{
    /**
     * @param  \Illuminate\Contracts\Pagination\LengthAwarePaginator<*, *>  $paginator
     *
     * @return array{
     *     count: int,
     *     currentPage: int,
     *     firstItem: int|null,
     *     hasMorePages: bool,
     *     lastItem: int|null,
     *     lastPage: int,
     *     perPage: int,
     *     total: int,
     * }
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
     * @template TKey of array-key
     * @template TValue
     *
     * @param  \Illuminate\Contracts\Pagination\Paginator<TKey, TValue>  $paginator
     *
     * @return array<TKey, TValue>
     */
    public function dataResolver(Paginator $paginator): array
    {
        return $paginator->items(); // @phpstan-ignore return.type (mismatch when Paginator has only 1 generic type)
    }
}
