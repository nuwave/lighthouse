<?php

namespace Nuwave\Lighthouse\Schema\Directives\Args;

use Illuminate\Database\Eloquent\Builder;
use Nuwave\Lighthouse\Schema\Values\ArgumentValue;
use Nuwave\Lighthouse\Schema\Directives\BaseDirective;
use Nuwave\Lighthouse\Support\Contracts\ArgMiddleware;
use Nuwave\Lighthouse\Support\Traits\HandlesQueryFilter;

class SearchDirective extends BaseDirective implements ArgMiddleware
{
    use HandlesQueryFilter;

    /**
     * Name of the directive.
     *
     * @return string
     */
    public function name(): string
    {
        return 'search';
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
        // Adds within method to specify custom index.
        $within = $this->directiveArgValue('within');

        $this->injectFilter(
            $argument,
            function (Builder $query, string $columnName, $value) use ($within) {
                $modelClass = get_class(
                    $query->getModel()
                );
                
                /** @var \Laravel\Scout\Builder $query */
                $query = $modelClass::search($value);

                if (! is_null($within)) {
                    $query->within($within);
                }

                return $query;
            }
        );

        return $next($argument);
    }
}
