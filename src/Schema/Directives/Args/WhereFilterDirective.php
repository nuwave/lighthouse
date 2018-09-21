<?php

namespace Nuwave\Lighthouse\Schema\Directives\Args;

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
     * @param \Closure       $next
     *
     * @return ArgumentValue
     */
    public function handleArgument(ArgumentValue $argument, \Closure $next)
    {
        $operator = $this->directiveArgValue('operator', '=');
        $clause = $this->directiveArgValue('clause');

        $this->injectFilter(
            $argument,
            function ($query, string $columnName, $value) use ($operator, $clause){
                return $clause
                    ? call_user_func_array([$query, $clause], [$columnName, $operator, $value])
                    : $query->where($columnName, $operator, $value);
            }
        );
        
        return $next($argument);
    }
}
