<?php

namespace Nuwave\Lighthouse\Schema\Types;

use GraphQL\Type\Definition\ResolveInfo;
use Nuwave\Lighthouse\Execution\Utils\Cursor;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

class ConnectionField
{
    /**
     * Resolve page info for connection.
     *
     * @param LengthAwarePaginator $paginator
     * @param array                $args
     * @param mixed                $context
     * @param ResolveInfo|null     $info
     *
     * @return array
     */
    public function pageInfoResolver(
        LengthAwarePaginator $paginator,
        array $args,
        $context = null,
        ResolveInfo $info = null
    ) {
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
     * @param LengthAwarePaginator $paginator
     * @param array                $args
     * @param mixed                $context
     * @param ResolveInfo|null     $info
     *
     * @return \Illuminate\Support\Collection
     */
    public function edgeResolver(
        LengthAwarePaginator $paginator,
        array $args,
        $context = null,
        ResolveInfo $info = null
    ) {
        $firstItem = $paginator->firstItem();

        return $paginator->values()->map(function ($item, $index) use ($firstItem) {
            return [
                'cursor' => Cursor::encode($firstItem + $index),
                'node' => $item
            ];
        });
    }
}
