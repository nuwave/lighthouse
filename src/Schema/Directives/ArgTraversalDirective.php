<?php declare(strict_types=1);

namespace Nuwave\Lighthouse\Schema\Directives;

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
    public function handleField(FieldValue $fieldValue): void
    {
        $fieldValue->addArgumentSetTransformer(fn (ArgumentSet $argumentSet): ArgumentSet => $this->transformRecursively($argumentSet));
    }

    protected function transformRecursively(ArgumentSet $argumentSet): ArgumentSet
    {
        foreach ($argumentSet->arguments as $argument) {
            $directivesForArray = $argument->directives->filter(
                Utils::instanceofMatcher(ArgDirectiveForArray::class),
            );
            $argument->value = $this->transform($argument->value, $directivesForArray);

            $directivesForArgument = $argument->directives->filter(
                Utils::instanceofMatcher(ArgDirective::class),
            );

            $argument->value = Utils::mapEach(
                function ($value) use ($directivesForArgument) {
                    if ($value instanceof ArgumentSet) {
                        $value = $this->transform($value, $directivesForArgument);

                        return $this->transformRecursively($value);
                    }

                    return $this->transform($value, $directivesForArgument);
                },
                $argument->value,
            );
        }

        return $argumentSet;
    }

    /**
     * @param  mixed  $value  The client given value
     * @param  \Illuminate\Support\Collection<int, \Nuwave\Lighthouse\Support\Contracts\Directive>  $directivesForArgument
     *
     * @return mixed The transformed value
     */
    protected function transform(mixed $value, Collection $directivesForArgument): mixed
    {
        foreach ($directivesForArgument as $directive) {
            $value = $this->applyDirective($directive, $value);
        }

        return $value;
    }

    /**
     * @param  mixed  $value  The client given value
     *
     * @return mixed The transformed value
     */
    abstract protected function applyDirective(Directive $directive, mixed $value): mixed;
}
