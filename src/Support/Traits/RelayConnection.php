<?php

namespace Nuwave\Lighthouse\Support\Traits;

use Closure;

trait RelayConnection
{
    use GlobalIdTrait;

    /**
     * Paginate connection w/ query args.
     *
     * @param  \Illuminate\Database\Eloquent\Builder $query
     * @param  array  $args
     * @return \Illuminate\Database\Eloquent\Builder
     */
    public function scopeGetConnection($query, array $args)
    {
        $first = isset($args['first']) ? $args['first'] : 15;
        $after = $this->decodeCursor($args);
        $page = isset($args['page']) ? $args['page'] : 1;
        $currentPage = $first && $after ? floor(($first + $after) / $first) : $page;

        return $query->paginate($first, ['*'], 'page', $currentPage);
    }

    /**
     * Load connection w/ args.
     *
     * @param  \Illuminate\Database\Eloquent\Builder $query
     * @param  array  $args
     * @return \Illuminate\Database\Eloquent\Builder
     */
    public function scopeLoadConnection($query, array $args)
    {
        $first = isset($args['first']) ? $args['first'] : 15;
        $after = $this->decodeCursor($args);
        $page = isset($args['page']) ? $args['page'] : 1;
        $currentPage = $first && $after ? floor(($first + $after) / $first) : $page;
        $skip = $first * $currentPage;

        return $query->take($first)->skip($skip);
    }

    /**
     * Get edge connectino.
     *
     * @param  Closure $closure
     * @return $this
     */
    public function getEdge(Closure $closure)
    {
        $cursor = $closure();
        $this->relayCursor = $this->encodeGlobalId('arrayconnection', $cursor);

        return $this;
    }
}
