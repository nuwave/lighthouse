<?php

namespace Nuwave\Lighthouse\Schema\Directives\Args;

use Illuminate\Database\Eloquent\Builder;
use Nuwave\Lighthouse\Schema\Directives\BaseDirective;
use Nuwave\Lighthouse\Schema\Values\ArgumentValue;
use Nuwave\Lighthouse\Support\Contracts\ArgMiddleware;
use Nuwave\Lighthouse\Support\Traits\HandlesQueryFilter;

class ScoutDirective extends BaseDirective implements ArgMiddleware
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
     *
     * @return ArgumentValue
     */
    public function handleArgument(ArgumentValue $argument): ArgumentValue
    {
        $arg = $argument->getArgName();

        // Adds within method to specify custom index.
        $within = $this->directiveArgValue('within');

        return $this->injectFilter(
            $argument, [
                'resolve' => function (Builder $query, $key, array $args) use ($arg, $within) {
                    $class = get_class($query->getModel());
                    /** @var \Laravel\Scout\Builder $query */
                    $query = $class::search(array_get($args, $arg));

                    if (! is_null($within)) {
                        $query->within($within);
                    }

                    return $query;
                },
            ]
        );
    }
}
