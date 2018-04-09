<?php

namespace Nuwave\Lighthouse\Schema\Types;

use GraphQL\Type\Definition\ResolveInfo;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

class PaginatorField
{
    /**
     * Resolve paginator info for connection.
     *
     * @param LengthAwarePaginator $root
     * @param array                $args
     * @param mixed                $context
     * @param ResolveInfo|null     $info
     *
     * @return array
     */
    public function paginatorInfoResolver(
        LengthAwarePaginator $root,
        array $args,
        $context = null,
        ResolveInfo $info = null
    ) {
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
     * @param LengthAwarePaginator $root
     * @param array                $args
     * @param mixed                $context
     * @param ResolveInfo|null     $info
     *
     * @return \Illuminate\Support\Collection
     */
    public function dataResolver(
        LengthAwarePaginator $root,
        array $args,
        $context = null,
        ResolveInfo $info = null
    ) {
        return $root->values();
    }
}
