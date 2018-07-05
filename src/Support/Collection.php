<?php

namespace Nuwave\Lighthouse\Support;

use Nuwave\Lighthouse\Support\DataLoader\QueryBuilder;

/**
 * Class Collection
 *
 * @mixin \Illuminate\Support\Collection
 *
 * @property array items
 */
class Collection
{
    /**
     * @return \Closure
     */
    public function fetch(): \Closure
    {
        return function ($relations = null) {
            if (count($this->items) > 0) {
                if (is_string($relations)) {
                    $relations = [$relations];
                }
                $query = $this->first()->newQuery()->with($relations);
                $this->items = app(QueryBuilder::class)->eagerLoadRelations($query, $this->items);
            }

            return $this;
        };
    }

    /**
     * @return \Closure
     */
    public function fetchCount(): \Closure
    {
        return function ($relations = null) {
            if (count($this->items) > 0) {
                if (is_string($relations)) {
                    $relations = [$relations];
                }

                $query = $this->first()->newQuery()->withCount($relations);
                $this->items = app(QueryBuilder::class)->eagerLoadCount($query, $this->items);
            }

            return $this;
        };
    }

    /**
     * @return \Closure
     */
    public function fetchForPage(): \Closure
    {
        return function ($perPage, $page, $relations) {
            if (count($this->items) > 0) {
                if (is_string($relations)) {
                    $relations = [$relations];
                }

                $this->items = $this->fetchCount($relations)->items;
                $query = $this->first()->newQuery()->with($relations);
                $this->items = app(QueryBuilder::class)
                    ->eagerLoadRelations($query, $this->items, $perPage, $page);
            }

            return $this;
        };
    }
}
