<?php

namespace Nuwave\Lighthouse\Schema\Directives\Args;

use Nuwave\Lighthouse\Schema\Directives\BaseDirective;
use Nuwave\Lighthouse\Schema\Values\ArgumentValue;
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
     *
     * @return ArgumentValue
     */
    public function handleArgument(ArgumentValue $argument): ArgumentValue
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
