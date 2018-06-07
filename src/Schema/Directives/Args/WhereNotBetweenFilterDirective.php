<?php

namespace Nuwave\Lighthouse\Schema\Directives\Args;

use Nuwave\Lighthouse\Schema\Directives\Args\ArgMiddleware;
use Nuwave\Lighthouse\Schema\Values\ArgumentValue;
use Nuwave\Lighthouse\Support\Traits\HandlesDirectives;
use Nuwave\Lighthouse\Support\Traits\HandlesQueryFilter;

class WhereNotBetweenFilterDirective implements ArgMiddleware
{
    use HandlesDirectives, HandlesQueryFilter;

    /**
     * Name of the directive.
     *
     * @return string
     */
    public static function name()
    {
        return 'whereNotBetween';
    }

    /**
     * Resolve the field directive.
     *
     * @param ArgumentValue $argument
     *
     * @return array
     */
    public function handleArgument(ArgumentValue $argument)
    {
        return $this->injectKeyedFilter($argument, [
            'resolve' => function ($query, $key, array $args) {
                $between = collect($args['resolveArgs'])->map(function ($arg) use ($args) {
                    return array_get($args, $arg);
                })->filter()->toArray();

                return $query->whereNotBetween($key, $between);
            },
        ]);
    }
}
