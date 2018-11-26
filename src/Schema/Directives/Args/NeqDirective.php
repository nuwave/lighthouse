<?php

namespace Nuwave\Lighthouse\Schema\Directives\Args;

use Nuwave\Lighthouse\Support\Contracts\ArgFilterDirective;

class NeqDirective implements ArgFilterDirective
{
    /**
     * Name of the directive.
     *
     * @return string
     */
    public function name(): string
    {
        return 'neq';
    }

    /**
     * Get the filter.
     *
     * @return \Closure
     */
    public function filter(): \Closure
    {
        return function ($query, string $columnName, $value) {
            return $query->where($columnName, '<>', $value);
        };
    }

    /**
     * Get the type of the ArgFilterDirective.
     *
     * @return string self::SINGLE_TYPE | self::MULTI_TYPE
     */
    public function type(): string
    {
        return static::SINGLE_TYPE;
    }
}
