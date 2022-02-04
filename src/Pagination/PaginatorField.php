<?php

namespace Nuwave\Lighthouse\Pagination;

use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;

class PaginatorField
{

    /**
     * Resolve total info for connection.
     *
     * @param  \Illuminate\Pagination\LengthAwarePaginator<mixed>  $root
     *
     * @return array<string, mixed>
     */
    public function paginatorInfoCountResolver(LengthAwarePaginator $root)
    {
        return $root->count();
    }

    /**
     * Resolve total info for connection.
     *
     * @param  \Illuminate\Pagination\LengthAwarePaginator<mixed>  $root
     *
     * @return array<string, mixed>
     */
    public function paginatorInfoTotalResolver(LengthAwarePaginator $root)
    {
        return $root->total();
    }
    /**
     * Resolve first item info for connection.
     *
     * @param  \Illuminate\Pagination\LengthAwarePaginator<mixed>  $root
     *
     * @return array<string, mixed>
     */
    public function paginatorInfoFirstItemResolver(LengthAwarePaginator $root)
    {
        return $root->firstItem();
    }

    /**
     * Resolve per page info for connection.
     *
     * @param  \Illuminate\Pagination\LengthAwarePaginator<mixed>  $root
     *
     * @return array<string, mixed>
     */
    public function paginatorInfoPerPageResolver(LengthAwarePaginator $root)
    {
        return $root->perPage();
    }

    /**
     * Resolve last item info for connection.
     *
     * @param  \Illuminate\Pagination\LengthAwarePaginator<mixed>  $root
     *
     * @return array<string, mixed>
     */
    public function paginatorInfoLastItemResolver(LengthAwarePaginator $root)
    {
        return $root->lastItem();
    }

    /**
     * Resolve current page info for connection.
     *
     * @param  \Illuminate\Pagination\LengthAwarePaginator<mixed>  $root
     *
     * @return array<string, mixed>
     */
    public function paginatorInfoCurrentPageResolver(LengthAwarePaginator $root)
    {
        return $root->currentPage();
    }

    /**
     * Resolve has more page info for connection.
     *
     * @param  \Illuminate\Pagination\LengthAwarePaginator<mixed>  $root
     *
     * @return array<string, mixed>
     */
    public function paginatorInfoHasMorePagesResolver(LengthAwarePaginator $root)
    {
        return $root->hasMorePages();
    }

    /**
     * Resolve last page info for connection.
     *
     * @param  \Illuminate\Pagination\LengthAwarePaginator<mixed>  $root
     *
     * @return array<string, mixed>
     */
    public function paginatorInfoLastPageResolver(LengthAwarePaginator $root)
    {
        return $root->lastPage();
    }

    /**
     * Resolve data for connection.
     *
     * @param  \Illuminate\Pagination\LengthAwarePaginator<mixed>  $root
     *
     * @return \Illuminate\Support\Collection<mixed>
     */
    public function dataResolver(LengthAwarePaginator $root): Collection
    {
        // @phpstan-ignore-next-line static refers to the wrong class because it is a proxied method call
        return $root->values();
    }
}
