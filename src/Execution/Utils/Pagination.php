<?php

namespace Nuwave\Lighthouse\Execution\Utils;

class Pagination
{
    /**
     * @param int $first
     * @param int $after
     * @param int $defaultPage
     * @return int
     */
    public static function calculateCurrentPage(int $first, int $after, int $defaultPage = 1): int
    {
        return $first && $after
            ? floor(($first + $after) / $first)
            : $defaultPage;
    }
}
