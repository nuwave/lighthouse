<?php


namespace Nuwave\Lighthouse\Support;
use Nuwave\Lighthouse\Support\DataLoader\QueryBuilder;


/**
 * Class Collection
 *
 * @package Nuwave\Lighthouse\Support
 * @mixin \Illuminate\Support\Collection
 * @property array items
 */
class Collection
{
    public function fetch()
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


    public function fetchCount()
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

    public function fetchForPage()
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

    public function toAssoc()
    {
        return function () {
            return $this->reduce(function ($assoc, $keyValuePair) {
                list($key, $value) = $keyValuePair;
                $assoc[$key] = $value;
                return $assoc;
            }, new static);
        };
    }

    public function mapToAssoc()
    {
        return function ($callback) {
            return $this->map($callback)->toAssoc();
        };
    }

}