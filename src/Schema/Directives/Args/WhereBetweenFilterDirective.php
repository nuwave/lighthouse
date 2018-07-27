<?php

namespace Nuwave\Lighthouse\Schema\Directives\Args;

use Nuwave\Lighthouse\Schema\Values\ArgumentValue;
use Nuwave\Lighthouse\Schema\Directives\BaseDirective;
use Nuwave\Lighthouse\Support\Contracts\ArgMiddleware;
use Nuwave\Lighthouse\Support\Traits\HandlesQueryFilter;

class WhereBetweenFilterDirective extends BaseDirective implements ArgMiddleware
{
    use HandlesQueryFilter;

    /**
     * Name of the directive.
     *
     * @return string
     */
    public function name()
    {
        return 'whereBetween';
    }

    /**
     * Resolve the field directive.
     *
     * @param ArgumentValue $argument
     * @param \Closure       $next
     *
     * @return ArgumentValue
     */
    public function handleArgument(ArgumentValue $argument, \Closure $next)
    {
        $argument = $this->injectKeyedFilter($argument, [
            'resolve' => function ($query, $key, array $args) {
                $between = collect($args['resolveArgs'])->map(function ($arg) use ($args) {
                    return array_get($args, $arg);
                })->filter()->toArray();

                return $query->whereBetween($key, $between);
            },
        ]);

        return $next($argument);
    }
}
