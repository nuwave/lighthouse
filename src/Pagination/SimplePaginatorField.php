<?php

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
     * @return array<string, mixed>
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
     * @return \Illuminate\Support\Collection<mixed>
     */
    public function dataResolver(Paginator $root): Collection
    {
        /**
         * The return type `static` refers to the wrong class because it is a proxied method call.
         *
         * @var \Illuminate\Support\Collection<mixed> $values
         */
        $values = $root->values();

        return $values;
    }
}
