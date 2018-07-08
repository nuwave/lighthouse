<?php

namespace Nuwave\Lighthouse\Schema\Directives\Args;

use Closure;
use Nuwave\Lighthouse\Schema\Values\ArgumentValue;
use Nuwave\Lighthouse\Schema\Directives\BaseDirective;
use Nuwave\Lighthouse\Support\Contracts\ArgMiddleware;
use Nuwave\Lighthouse\Support\Traits\HandlesQueryFilter;

class WhereFilterDirective extends BaseDirective implements ArgMiddleware
{
    use HandlesQueryFilter;

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
     * @param Closure       $next
     *
     * @return ArgumentValue
     */
    public function handleArgument(ArgumentValue $argument, Closure $next)
    {
        $arg = $argument->getArgName();

        $operator = $this->directiveArgValue('operator', '=');
        $clause = $this->directiveArgValue('clause');

        $argument = $this->injectFilter($argument, [
            'resolve' => function ($query, $key, array $args) use ($arg, $operator, $clause) {
                $value = array_get($args, $arg);

                return $clause
                    ? call_user_func_array([$query, $clause], [$key, $operator, $value])
                    : $query->where($key, $operator, $value);
            },
        ]);

        return $next($argument);
    }
}
