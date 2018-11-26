<?php

namespace Nuwave\Lighthouse\Schema\Directives\Args;

use Nuwave\Lighthouse\Support\Contracts\ArgFilterDirective;

class WhereNotBetweenDirective implements ArgFilterDirective
{
    /**
     * Name of the directive.
     *
     * @return string
     */
    public function name(): string
    {
        return 'whereNotBetween';
    }

    /**
     * Get the type of the ArgFilterDirective.
     *
     * @return string self::SINGLE_TYPE | self::MULTI_TYPE
     */
    public function type(): string
    {
        return static::MULTI_TYPE;
    }

    /**
     * Get the filter.
     *
     * @return \Closure
     */
    public function filter(): \Closure
    {
        return function ($query, string $columnName, array $values) {
            return $query->whereNotBetween($columnName, $values);
        };
    }
}
