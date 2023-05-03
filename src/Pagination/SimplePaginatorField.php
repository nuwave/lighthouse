<?php declare(strict_types=1);

namespace Nuwave\Lighthouse\Pagination;

use Illuminate\Contracts\Pagination\Paginator;
use Illuminate\Support\Collection;

class SimplePaginatorField
{
    /**
     * Resolve simple paginator info for connection.
     *
     * @param  \Illuminate\Pagination\Paginator<mixed>  $root
     *
     * @return array{
     *     count: int,
     *     currentPage: int,
     *     firstItem: int|null,
     *     lastItem: int|null,
     *     perPage: int,
     *     hasMorePages: bool,
     * }
     */
    public function paginatorInfoResolver(Paginator $root): array
    {
        return [
            'count' => $root->count(),
            'currentPage' => $root->currentPage(),
            'firstItem' => $root->firstItem(),
            'lastItem' => $root->lastItem(),
            'perPage' => $root->perPage(),
            'hasMorePages' => $root->hasMorePages(),
        ];
    }

    /**
     * Resolve data for connection.
     *
     * @param  \Illuminate\Pagination\Paginator<mixed>  $root
     *
     * @return \Illuminate\Support\Collection<int, mixed>
     */
    public function dataResolver(Paginator $root): Collection
    {
        return $root->values();
    }
}
