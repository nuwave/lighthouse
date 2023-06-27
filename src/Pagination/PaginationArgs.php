<?php

namespace Nuwave\Lighthouse\Pagination;

use GraphQL\Error\Error;
use Illuminate\Contracts\Pagination\Paginator;
use Illuminate\Support\Arr;
use Laravel\Scout\Builder as ScoutBuilder;
use Illuminate\Pagination\Paginator as BuilderPaginator;
use Illuminate\Container\Container;
use Illuminate\Pagination\LengthAwarePaginator;


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

        // if ($instance->first <= 0) {
        //     throw new Error(
        //         self::requestedZeroOrLessItems($instance->first)
        //     );
        // }

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
     * @param  \Illuminate\Database\Query\Builder|\Laravel\Scout\Builder|\Illuminate\Database\Eloquent\Builder|\Illuminate\Database\Eloquent\Relations\Relation  $builder
     */
    public function applyToBuilder(object $builder): Paginator
    {
        $methodName = $this->type->isSimple()
            ? 'simplePaginate'
            : 'paginate';
        if ($builder instanceof ScoutBuilder) {
            return $builder->{$methodName}($this->first, 'page', $this->page);
        }
        if($methodName=='paginate' && $this->first<0){
            $page = 1 ;
            
                $results=$builder->get(['*']);
                $total=$results->count();
            return $this->paginator($results, $total, $total, $page, [
                'path' => BuilderPaginator::resolveCurrentPath(),
                'pageName' => 'page',
            ]);
        }


        // @phpstan-ignore-next-line Relation&Builder mixin not recognized
        return $builder->{$methodName}($this->first, ['*'], 'page', $this->page);
    }

    /**
     * Create a new length-aware paginator instance.
     *
     * @param  \Illuminate\Support\Collection  $items
     * @param  int  $total
     * @param  int  $perPage
     * @param  int  $currentPage
     * @param  array  $options
     * @return \Illuminate\Pagination\LengthAwarePaginator
     */

    protected function paginator($items, $total, $perPage, $currentPage, $options)
    {
        if($this->first<0){
            $perPage=-1;
        }
        return Container::getInstance()->makeWith(LengthAwarePaginator::class, compact(
            'items', 'total', 'perPage', 'currentPage', 'options'
        ));
    }
}
