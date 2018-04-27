<?php

namespace Nuwave\Lighthouse\Support\Database;

use Nuwave\Lighthouse\Schema\Values\ArgumentValue;

class QueryFilter
{
    /**
     * Collection of query filters.
     *
     * @var array
     */
    protected $filters = [];

    /**
     * Get query filter instance for field.
     *
     * @param ArgumentValue $value
     *
     * @return self
     */
    public static function getInstance(ArgumentValue $value)
    {
        $handler = sprintf(
            'query.filter.%s.%s',
            strtolower($value->getField()->getNodeName()),
            strtolower($value->getField()->getFieldName())
        );

        return app()->has($handler)
            ? resolve($handler)
            : app()->instance($handler, resolve(static::class));
    }

    /**
     * Build query with filter(s).
     *
     * @param \Illuminate\Database\Query\Builder $query
     * @param array                              $args
     *
     * @return \Illuminate\Database\Query\Builder
     */
    public static function build($query, array $args)
    {
        $instance = array_get($args, 'query.filter');

        return $instance
            ? $instance->filter($query, array_except($args, 'query.filter'))
            : $query;
    }

    /**
     * Run query through filter.
     *
     * @param \Illuminate\Database\Query\Builder $query
     * @param array                              $args
     *
     * @return \Illuminate\Database\Query\Builder
     */
    public function filter($query, array $args = [])
    {
        foreach ($this->filters as $key => $filter) {
            $resolve = $filter['resolve'];
            $resolveArgs = array_merge($args, [
                'resolveArgs' => array_get($filter, 'resolveArgs', []),
            ]);

            $query = $resolve($query, $key, $resolveArgs);
        }

        return $query;
    }

    /**
     * Get collection of filters.
     *
     * @return array
     */
    public function getFilters()
    {
        return $this->filters;
    }

    /**
     * Get filter by key.
     *
     * @param string $key
     * @param array  $default
     *
     * @return array|null
     */
    public function getFilter($key, $default = null)
    {
        return array_get($this->filters, $key, $default);
    }

    /**
     * Set filter by key.
     *
     * @param string $key
     * @param array  $filter
     */
    public function setFilter($key, array $filter)
    {
        $this->filters[$key] = $filter;
    }

    /**
     * Set collection of filters.
     *
     * @param array $filters
     */
    public function setFilters(array $filters)
    {
        $this->filters = $filters;
    }
}
