<?php

namespace Nuwave\Lighthouse\Schema\Directives\Args;

use Nuwave\Lighthouse\Schema\Directives\Args\ArgMiddleware;
use Nuwave\Lighthouse\Schema\Values\ArgumentValue;
use Nuwave\Lighthouse\Support\Traits\HandlesDirectives;
use Nuwave\Lighthouse\Support\Traits\HandlesQueryFilter;

class InFilterDirective implements ArgMiddleware
{
    use HandlesDirectives, HandlesQueryFilter;

    /**
     * Name of the directive.
     *
     * @return string
     */
    public static function name()
    {
        return 'in';
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
        $arg = $argument->getArgName();

        return $this->injectFilter($argument, [
            'resolve' => function ($query, $key, array $args) use ($arg) {
                return $query->whereIn($key, array_get($args, $arg));
            },
        ]);
    }
}
