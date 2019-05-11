<?php

namespace Nuwave\Lighthouse\Pagination;

use GraphQL\Error\Error;
use Illuminate\Support\Arr;

class PaginationUtils
{
    /**
     * Calculate the current page to inform the user about the pagination state.
     *
     * @param  int  $first
     * @param  int  $after
     * @param  int  $defaultPage
     * @return int
     */
    public static function calculateCurrentPage(int $first, int $after, int $defaultPage = 1): int
    {
        return $first && $after
            ? (int) floor(($first + $after) / $first)
            : $defaultPage;
    }

    /**
     * @param  mixed[]  $args
     * @param  \Nuwave\Lighthouse\Pagination\PaginationType|null  $paginationType
     * @param  int|null  $paginateMaxCount
     * @return int[] A pair consisting of first and page
     *
     * @throws \GraphQL\Error\Error
     */
    public static function extractArgs(array $args, ?PaginationType $paginationType, ?int $paginateMaxCount): array
    {
        if ($paginationType->isConnection()) {
            /** @var int $first */
            $first = $args['first'];
            $page = self::calculateCurrentPage(
                $first,
                Cursor::decode($args)
            );
        } else {
            /** @var int $first */
            $first = $args[config('lighthouse.pagination_amount_argument')];
            $page = Arr::get($args, 'page', 1);
        }

        if ($first <= 0) {
            throw new Error(
                "Requested pagination amount must be more than 0, got $first"
            );
        }

        // Make sure the maximum pagination count is not exceeded
        if (
            $paginateMaxCount !== null
            && $first > $paginateMaxCount
        ) {
            throw new Error(
                "Maximum number of {$paginateMaxCount} requested items exceeded. Fetch smaller chunks."
            );
        }

        return [$first, $page];
    }
}
