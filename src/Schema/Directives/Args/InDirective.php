<?php

namespace Nuwave\Lighthouse\Schema\Directives\Args;

use Nuwave\Lighthouse\Support\Contracts\ArgFilterDirective;

class InDirective implements ArgFilterDirective
{
    /**
     * Name of the directive.
     *
     * @return string
     */
    public function name(): string
    {
        return 'in';
    }

    /**
     * Get the filter.
     *
     * @return \Closure
     */
    public function filter(): \Closure
    {
        return function ($query, string $columnName, array $values) {
            return $query->whereIn($columnName, $values);
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
