<?php declare(strict_types=1);

namespace Nuwave\Lighthouse\Pagination;

use GraphQL\Error\Error;
use Illuminate\Contracts\Pagination\Paginator;
use Illuminate\Database\Eloquent\Builder as EloquentBuilder;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Database\Query\Builder as QueryBuilder;
use Illuminate\Support\Arr;
use Laravel\Scout\Builder as ScoutBuilder;

class PaginationArgs
{
    public function __construct(
        public int $page,
        public int $first,
        public PaginationType $type,
    ) {}

    /**
     * Create a new instance from user given args.
     *
     * @param  array<string, mixed>  $args
     */
    public static function extractArgs(array $args, PaginationType $paginationType, ?int $paginateMaxCount): self
    {
        $first = $args['first'];
        $page = $paginationType->isConnection()
            ? self::calculateCurrentPage(
                $first,
                Cursor::decode($args),
            )
            // Handles cases "paginate" and "simple", which both take the same args.
            : Arr::get($args, 'page', 1);

        if ($first < 0) {
            throw new Error(
                self::requestedLessThanZeroItems($first),
            );
        }

        // Make sure the maximum pagination count is not exceeded
        if (
            $paginateMaxCount !== null
            && $first > $paginateMaxCount
        ) {
            throw new Error(
                self::requestedTooManyItems($paginateMaxCount, $first),
            );
        }

        return new static($page, $first, $paginationType);
    }

    public static function requestedLessThanZeroItems(int $amount): string
    {
        return "Requested pagination amount must be non-negative, got {$amount}.";
    }

    public static function requestedTooManyItems(int $maxCount, int $actualCount): string
    {
        return "Maximum number of {$maxCount} requested items exceeded, got {$actualCount}. Fetch smaller chunks.";
    }

    /** Calculate the current page to inform the user about the pagination state. */
    protected static function calculateCurrentPage(int $first, int $after, int $defaultPage = 1): int
    {
        return $first && $after
            ? (int) floor(($first + $after) / $first)
            : $defaultPage;
    }

    /**
     * Apply the args to a builder, constructing a paginator.
     *
     * @template TModel of \Illuminate\Database\Eloquent\Model
     *
     * @param  \Illuminate\Database\Query\Builder|\Laravel\Scout\Builder|\Illuminate\Database\Eloquent\Builder<TModel>|\Illuminate\Database\Eloquent\Relations\Relation<TModel>  $builder
     *
     * @return \Illuminate\Contracts\Pagination\Paginator<TModel>
     */
    public function applyToBuilder(QueryBuilder|ScoutBuilder|EloquentBuilder|Relation $builder): Paginator
    {
        $methodName = $this->type->isSimple()
            ? 'simplePaginate'
            : 'paginate';

        if ($builder instanceof ScoutBuilder) {
            return $builder->{$methodName}($this->first, 'page', $this->page);
        }

        return $builder->{$methodName}($this->first, ['*'], 'page', $this->page);
    }
}
