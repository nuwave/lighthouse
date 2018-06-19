<?php

namespace Nuwave\Lighthouse\Schema\Directives\Args;

use Nuwave\Lighthouse\Schema\Values\ArgumentValue;
use Nuwave\Lighthouse\Support\Contracts\ArgMiddleware;
use Nuwave\Lighthouse\Support\Traits\HandlesDirectives;
use Nuwave\Lighthouse\Support\Traits\HandlesQueryFilter;

class WhereFilterDirective implements ArgMiddleware
{
    use HandlesDirectives, HandlesQueryFilter;

    /**
     * Name of the directive.
     *
     * @return string
     */
    public function name()
    {
        return 'where';
    }

    /**
     * Resolve the field directive.
     *
     * @param ArgumentValue $argument
     *
     * @return ArgumentValue
     */
    public function handleArgument(ArgumentValue $argument)
    {
        $arg = $argument->getArgName();

        $operator = $this->directiveArgValue(
            $this->queryFilterDirective($argument),
            'operator',
            '='
        );

        $clause = $this->directiveArgValue(
            $this->queryFilterDirective($argument),
            'clause'
        );

        return $this->injectFilter($argument, [
            'resolve' => function ($query, $key, array $args) use ($arg, $operator, $clause) {
                $value = array_get($args, $arg);

                return $clause
                    ? call_user_func_array([$query, $clause], [$key, $operator, $value])
                    : $query->where($key, $operator, $value);
            },
        ]);
    }
}
