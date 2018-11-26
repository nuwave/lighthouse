<?php

namespace Nuwave\Lighthouse\Schema\Directives\Args;

use Nuwave\Lighthouse\Schema\Directives\BaseDirective;
use Nuwave\Lighthouse\Support\Contracts\ArgFilterDirective;

class WhereDirective extends BaseDirective implements ArgFilterDirective
{
    /**
     * Name of the directive.
     *
     * @return string
     */
    public function name(): string
    {
        return 'where';
    }

    /**
     * Get the filter.
     *
     * @return \Closure
     */
    public function filter(): \Closure
    {
        $operator = $this->directiveArgValue('operator', '=');
        $clause = $this->directiveArgValue('clause');

        return function ($query, string $columnName, $value) use ($operator, $clause) {
            return $clause
                ? $query->{$clause}($columnName, $operator, $value)
                : $query->where($columnName, $operator, $value);
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
