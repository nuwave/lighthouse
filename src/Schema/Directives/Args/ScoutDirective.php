<?php


namespace Nuwave\Lighthouse\Schema\Directives\Args;

use Illuminate\Database\Eloquent\Builder;
use Nuwave\Lighthouse\Schema\Values\ArgumentValue;
use Nuwave\Lighthouse\Support\Contracts\ArgMiddleware;
use Nuwave\Lighthouse\Support\Traits\HandlesDirectives;
use Nuwave\Lighthouse\Support\Traits\HandlesQueryFilter;

class ScoutDirective implements ArgMiddleware
{
    use HandlesDirectives, HandlesQueryFilter;

    /**
     * Name of the directive.
     *
     * @return string
     */
    public function name()
    {
        return "search";
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

        // Adds within method to specify custom index.
        $within = $this->directiveArgValue(
            $this->queryFilterDirective($argument),
            'within',
            null
        );

        return $this->injectFilter(
            $argument, [
                'resolve' => function (Builder $query, $key, array $args) use ($arg, $within) {
                    $class = get_class($query->getModel());
                    /** @var \Laravel\Scout\Builder $query */
                    $query = $class::search(array_get($args, $arg));

                    if(!is_null($within)) {
                        $query->within($within);
                    }

                    return $query;
                },
            ]
        );
    }
}