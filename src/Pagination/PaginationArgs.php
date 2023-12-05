<?php declare(strict_types=1);

namespace Nuwave\Lighthouse\Pagination;

use GraphQL\Error\Error;
use Illuminate\Contracts\Pagination\Paginator;
use Illuminate\Database\Eloquent\Builder as EloquentBuilder;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Database\Query\Builder as QueryBuilder;
use Illuminate\Support\Arr;
use Laravel\Scout\Builder as ScoutBuilder;
use Nuwave\Lighthouse\Cache\CacheDirective;
use Nuwave\Lighthouse\Execution\ResolveInfo;
use Nuwave\Lighthouse\Support\Contracts\Directive;

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
    public static function extractArgs(array $args, ResolveInfo $resolveInfo, PaginationType $proposedPaginationType, ?int $paginateMaxCount): self
    {
        $first = $args['first'];

        $page = $proposedPaginationType->isConnection()
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

        $optimalPaginationType = self::optimalPaginationType($proposedPaginationType, $resolveInfo);

        return new static($page, $first, $optimalPaginationType);
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

    protected static function optimalPaginationType(PaginationType $proposedType, ResolveInfo $resolveInfo): PaginationType
    {
        // Already the most optimal type.
        if ($proposedType->isSimple()) {
            return $proposedType;
        }

        // If the result may be used in a cache, we always want to retrieve and store the full pagination data.
        // Even though the query that initially creates the cache may not need additional information such as
        // the total counts, following queries may need them - and use the same cached value.
        $hasCacheDirective = $resolveInfo->argumentSet
            ->directives
            ->contains(static fn (Directive $directive): bool => $directive instanceof CacheDirective);
        if ($hasCacheDirective) {
            return $proposedType;
        }

        // If the page info is not requested, we can save a database query by using the simple paginator.
        // In contrast to the full pagination, it does not query total counts.
        if (! isset($resolveInfo->getFieldSelection()[$proposedType->infoFieldName()])) {
            return new PaginationType(PaginationType::SIMPLE);
        }

        return $proposedType;
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
        if ($this->first === 0) {
            if ($this->type->isSimple()) {
                return new ZeroPerPagePaginator($this->page);
            }

            $total = $builder instanceof ScoutBuilder
                ? 0 // Laravel\Scout\Builder exposes no method to get the total count
                : $builder->count(); // @phpstan-ignore-line see Illuminate\Database\Query\Builder::count(), available as a mixin in the other classes

            return new ZeroPerPageLengthAwarePaginator($total, $this->page);
        }

        $methodName = $this->type->isSimple()
            ? 'simplePaginate'
            : 'paginate';

        if ($builder instanceof ScoutBuilder) {
            return $builder->{$methodName}($this->first, 'page', $this->page);
        }

        return $builder->{$methodName}($this->first, ['*'], 'page', $this->page);
    }
}
