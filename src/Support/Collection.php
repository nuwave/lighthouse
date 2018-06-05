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

    public function flattenKeepKeys()
    {
        return function ($depth = 1, $dotNotation = false) {
            if ($depth) {
                $newArray = [];
                foreach ($this->items as $parentKey => $value) {
                    if (is_array($value)) {
                        $valueKeys = array_keys($value);
                        foreach ($valueKeys as $key) {
                            $subValue = $value[$key];
                            $newKey = $key;
                            if ($dotNotation) {
                                $newKey = "$parentKey.$key";
                                if ($dotNotation !== true) {
                                    $newKey = "$dotNotation.$newKey";
                                }

                                if (is_array($value[$key])) {
                                    $subValue = collect($value[$key])->flattenKeepKeys($depth - 1, $newKey)->toArray();
                                }
                            }
                            $newArray[$newKey] = $subValue;
                        }
                    } else {
                        $newArray[$parentKey] = $value;
                    }
                }

                $this->items = collect($newArray)->flattenKeepKeys(--$depth, $dotNotation)->toArray();
            }

            return collect($this->items);
        };
    }

}