<?php

namespace Nuwave\Lighthouse\Schema\Directives;

use Closure;
use Illuminate\Support\Collection;
use Nuwave\Lighthouse\Execution\Arguments\ArgumentSet;
use Nuwave\Lighthouse\Schema\Values\FieldValue;
use Nuwave\Lighthouse\Support\Contracts\ArgDirective;
use Nuwave\Lighthouse\Support\Contracts\ArgDirectiveForArray;
use Nuwave\Lighthouse\Support\Contracts\Directive;
use Nuwave\Lighthouse\Support\Contracts\FieldMiddleware;
use Nuwave\Lighthouse\Support\Utils;

abstract class ArgTraversalDirective extends BaseDirective implements FieldMiddleware
{
    public function handleField(FieldValue $fieldValue, Closure $next): FieldValue
    {
        $fieldValue->addArgumentSetTransformer(function (ArgumentSet $argumentSet): ArgumentSet {
            return $this->transformRecursively($argumentSet);
        });

        return $next($fieldValue);
    }

    public function transformRecursively(ArgumentSet $argumentSet): ArgumentSet
    {
        foreach ($argumentSet->arguments as $argument) {
            $directivesForArray = $argument->directives->filter(
                Utils::instanceofMatcher(ArgDirectiveForArray::class)
            );
            $argument->value = $this->transform($argument->value, $directivesForArray);

            $directivesForArgument = $argument->directives->filter(
                Utils::instanceofMatcher(ArgDirective::class)
            );

            $argument->value = Utils::applyEach(
                function ($value) use ($directivesForArgument) {
                    if ($value instanceof ArgumentSet) {
                        $value = $this->transform($value, $directivesForArgument);

                        return $this->transformRecursively($value);
                    } else {
                        return $this->transform($value, $directivesForArgument);
                    }
                },
                $argument->value
            );
        }

        return $argumentSet;
    }

    /**
     * @param  mixed  $value The client given value
     * @return mixed The transformed value
     */
    protected function transform($value, Collection $directivesForArgument)
    {
        foreach ($directivesForArgument as $directive) {
            $value = $this->applyDirective($directive, $value);
        }

        return $value;
    }

    /**
     * @param  mixed  $value The client given value
     * @return mixed The transformed value
     */
    abstract protected function applyDirective(Directive $directive, $value);
}
