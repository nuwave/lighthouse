<?php

namespace Nuwave\Lighthouse\Schema\Types;

use GraphQL\Type\Definition\ResolveInfo;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Nuwave\Lighthouse\Schema\Execution\Utils\GlobalIdUtil;

class ConnectionField
{
    /**
     * Resolve page info for connection.
     *
     * @param LengthAwarePaginator $root
     * @param array                $args
     * @param mixed                $context
     * @param ResolveInfo|null     $info
     *
     * @return array
     */
    public function pageInfoResolver(
        LengthAwarePaginator $root,
        array $args,
        $context = null,
        ResolveInfo $info = null
    ) {
        $total = $root->total();
        $count = $root->count();
        $currentPage = $root->currentPage();
        $lastPage = $root->lastPage();
        $hasNextPage = $root->hasMorePages();
        $hasPreviousPage = $root->currentPage() > 1;
        $startCursor = GlobalIdUtil::encodeGlobalId(
            'arrayconnection',
            $root->firstItem()
        );
        $endCursor = GlobalIdUtil::encodeGlobalId(
            'arrayconnection',
            $root->lastItem()
        );

        return compact(
            'total',
            'count',
            'currentPage',
            'lastPage',
            'hasNextPage',
            'hasPreviousPage',
            'startCursor',
            'endCursor'
        );
    }

    /**
     * Resolve edges for connection.
     *
     * @param LengthAwarePaginator $root
     * @param array                $args
     * @param mixed                $context
     * @param ResolveInfo|null     $info
     *
     * @return \Illuminate\Support\Collection
     */
    public function edgeResolver(
        LengthAwarePaginator $root,
        array $args,
        $context = null,
        ResolveInfo $info = null
    ) {
        $first = $root->firstItem();

        return $root->values()->map(function ($item, $x) use ($first) {
            $cursor = $first + $x;
            $encodedCursor = GlobalIdUtil::encodeGlobalId('arrayconnection', $cursor);

            return ['cursor' => $encodedCursor, 'node' => $item];
        });
    }
}
