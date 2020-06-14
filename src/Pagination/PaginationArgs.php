<?php

namespace Nuwave\Lighthouse\Pagination;

use GraphQL\Error\Error;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Arr;
use Laravel\Scout\Builder as ScoutBuilder;

class PaginationArgs
{
    /**
     * @var int
     */
    public $page;

    /**
     * @var int
     */
    public $first;

    /**
     * Create a new instance from user given args.
     *
     * @param  mixed[]  $args
     * @param  \Nuwave\Lighthouse\Pagination\PaginationType  $paginationType
     * @return static
     *
     * @throws \GraphQL\Error\Error
     */
    public static function extractArgs(array $args, PaginationType $paginationType, ?int $paginateMaxCount): self
    {
        $instance = new static();

        if ($paginationType->isConnection()) {
            $instance->first = $args['first'];
            $instance->page = self::calculateCurrentPage(
                $instance->first,
                Cursor::decode($args)
            );
        } else {
            $instance->first = $args[config('lighthouse.pagination_amount_argument')];
            $instance->page = Arr::get($args, 'page', 1);
        }

        if ($instance->first <= 0) {
            throw new Error(
                self::requestedZeroOrLessItems($instance->first)
            );
        }

        // Make sure the maximum pagination count is not exceeded
        if (
            $paginateMaxCount !== null
            && $instance->first > $paginateMaxCount
        ) {
            throw new Error(
                self::requestedTooManyItems($paginateMaxCount, $instance->first)
            );
        }

        return $instance;
    }

    public static function requestedZeroOrLessItems(int $amount): string
    {
        return "Requested pagination amount must be more than 0, got {$amount}.";
    }

    public static function requestedTooManyItems(int $maxCount, int $actualCount): string
    {
        return "Maximum number of {$maxCount} requested items exceeded, got {$actualCount}. Fetch smaller chunks.";
    }

    /**
     * Calculate the current page to inform the user about the pagination state.
     */
    protected static function calculateCurrentPage(int $first, int $after, int $defaultPage = 1): int
    {
        return $first && $after
            ? (int) floor(($first + $after) / $first)
            : $defaultPage;
    }

    /**
     * Apply the args to a builder, constructing a paginator.
     *
     * @param \Illuminate\Database\Query\Builder|\Laravel\Scout\Builder|\Illuminate\Database\Eloquent\Relations\Relation $builder
     */
    public function applyToBuilder($builder): LengthAwarePaginator
    {
        if ($builder instanceof ScoutBuilder) {
            return $builder->paginate($this->first, 'page', $this->page);
        }

        return $builder->paginate($this->first, ['*'], 'page', $this->page);
    }
}
