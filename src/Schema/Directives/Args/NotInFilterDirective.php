<?php

namespace Nuwave\Lighthouse\Schema\Directives\Args;

use Closure;
use Nuwave\Lighthouse\Schema\Values\ArgumentValue;
use Nuwave\Lighthouse\Support\Contracts\ArgMiddleware;
use Nuwave\Lighthouse\Support\Traits\HandlesDirectives;
use Nuwave\Lighthouse\Support\Traits\HandlesQueryFilter;

class NotInFilterDirective implements ArgMiddleware
{
    use HandlesDirectives, HandlesQueryFilter;

    /**
     * Name of the directive.
     *
     * @return string
     */
    public function name()
    {
        return 'notIn';
    }

    /**
     * Resolve the field directive.
     *
     * @param ArgumentValue $argument
     *
     * @param Closure $next
     * @return ArgumentValue
     */
    public function handleArgument(ArgumentValue $argument, Closure $next)
    {
        $arg = $argument->getArgName();

        $this->injectFilter($argument, [
            'resolve' => function ($query, $key, array $args) use ($arg) {
                return $query->whereNotIn($key, array_get($args, $arg));
            },
        ]);

        return $next($argument);
    }
}
