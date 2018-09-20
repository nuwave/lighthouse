<?php

namespace Nuwave\Lighthouse\Schema\Directives\Args;

use Nuwave\Lighthouse\Schema\Values\ArgumentValue;
use Nuwave\Lighthouse\Schema\Directives\BaseDirective;
use Nuwave\Lighthouse\Support\Contracts\ArgMiddleware;
use Nuwave\Lighthouse\Support\Traits\HandlesQueryFilter;

class WhereNotBetweenFilterDirective extends BaseDirective implements ArgMiddleware
{
    use HandlesQueryFilter;

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
     * Resolve the field directive.
     *
     * @param ArgumentValue $argument
     * @param \Closure       $next
     *
     * @return ArgumentValue
     */
    public function handleArgument(ArgumentValue $argument, \Closure $next): ArgumentValue
    {
        $this->injectMultiArgumentFilter(
            $argument,
            function ($query, string $columnName, array $values) {
            
                return $query->whereNotBetween($columnName, $values);
            },
            self::name()
        );

        return $next($argument);
    }
}
