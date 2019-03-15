<?php

namespace Nuwave\Lighthouse\Execution\Utils;

use GraphQL\Error\Error;

class Pagination
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
     * Check the request number of paginated items against the defined maximum.
     *
     * @param  int|null  $maxCount
     * @param  int  $requestedCount
     * @throws \GraphQL\Error\Error
     */
    public static function throwIfPaginateMaxCountExceeded(?int $maxCount, int $requestedCount): void
    {
        if (
            $maxCount !== null
            && $requestedCount > $maxCount
        ) {
            throw new Error(
                "Maximum number of {$maxCount} requested items exceeded. Fetch smaller chunks."
            );
        }
    }
}
