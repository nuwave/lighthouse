<?php

namespace Nuwave\Lighthouse\Pagination;

use GraphQL\Error\Error;
use Illuminate\Contracts\Pagination\Paginator;
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
     * @var \Nuwave\Lighthouse\Pagination\PaginationType
     */
    public $type;

    /**
     * Create a new instance from user given args.
     *
     * @param  array<string, mixed>  $args
     *
     * @throws \GraphQL\Error\Error
     */
    public static function extractArgs(array $args, PaginationType $paginationType, ?int $paginateMaxCount): self
    {
        $instance = new static();

        $instance->type = $paginationType;

        if ($paginationType->isConnection()) {
            $instance->first = $args['first'];
            $instance->page = self::calculateCurrentPage(
                $instance->first,
                Cursor::decode($args)
            );
        } else {
            // Handles cases "paginate" and "simple", which both take the same args.
            $instance->first = $args['first'];
            $instance->page = Arr::get($args, 'page', 1);
        }

        if ($instance->first < 0) {
            throw new Error(
                self::requestedLessThanZeroItems($instance->first)
            );
        }

        // Make sure the maximum pagination count is not exceeded
        if (
            null !== $paginateMaxCount
            && $instance->first > $paginateMaxCount
        ) {
            throw new Error(
                self::requestedTooManyItems($paginateMaxCount, $instance->first)
            );
        }

        return $instance;
    }

    public static function requestedLessThanZeroItems(int $amount): string
    {
        return "Requested pagination amount must be non-negative, got {$amount}.";
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
     * @param  \Illuminate\Database\Query\Builder|\Laravel\Scout\Builder|\Illuminate\Database\Eloquent\Builder|\Illuminate\Database\Eloquent\Relations\Relation  $builder
     */
    public function applyToBuilder(object $builder): Paginator
    {
        $methodName = $this->type->isSimple()
            ? 'simplePaginate'
            : 'paginate';

        if ($builder instanceof ScoutBuilder) {
            // TODO remove fallback when requiring Laravel 8.6.0+
            if ($this->type->isSimple() && ! method_exists($builder, 'simplePaginate')) {
                $methodName = 'paginate';
            }

            return $builder->{$methodName}($this->first, 'page', $this->page);
        }

        // @phpstan-ignore-next-line Relation&Builder mixin not recognized
        return $builder->{$methodName}($this->first, ['*'], 'page', $this->page);
    }
}
