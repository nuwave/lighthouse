<?php

namespace Nuwave\Lighthouse\Schema\Directives;

use Closure;
use GraphQL\Type\Definition\ResolveInfo;
use Illuminate\Support\Collection;
use Nuwave\Lighthouse\Execution\Arguments\ArgumentSet;
use Nuwave\Lighthouse\Schema\Values\FieldValue;
use Nuwave\Lighthouse\Support\Contracts\ArgDirective;
use Nuwave\Lighthouse\Support\Contracts\ArgDirectiveForArray;
use Nuwave\Lighthouse\Support\Contracts\DefinedDirective;
use Nuwave\Lighthouse\Support\Contracts\Directive;
use Nuwave\Lighthouse\Support\Contracts\FieldMiddleware;
use Nuwave\Lighthouse\Support\Contracts\GraphQLContext;
use Nuwave\Lighthouse\Support\Utils;

abstract class ArgTraversalDirective extends BaseDirective implements FieldMiddleware, DefinedDirective
{
    /**
     * Resolve the field directive.
     *
     * @param  \Nuwave\Lighthouse\Schema\Values\FieldValue  $fieldValue
     * @param  \Closure  $next
     * @return \Nuwave\Lighthouse\Schema\Values\FieldValue
     */
    public function handleField(FieldValue $fieldValue, Closure $next): FieldValue
    {
        $resolver = $fieldValue->getResolver();

        return $next(
            $fieldValue->setResolver(
                function ($root, array $args, GraphQLContext $context, ResolveInfo $resolveInfo) use ($resolver) {
                    $resolveInfo->argumentSet = $this->transformRecursively($resolveInfo->argumentSet);

                    return $resolver(
                        $root,
                        $resolveInfo->argumentSet->toArray(),
                        $context,
                        $resolveInfo
                    );
                }
            )
        );
    }

    public function transformRecursively(ArgumentSet $argumentSet)
    {
        foreach ($argumentSet->arguments as $name => $argument) {
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

    protected function transform($value, Collection $directivesForArgument)
    {
        foreach ($directivesForArgument as $directive) {
            $value = $this->applyDirective($directive, $value);
        }

        return $value;
    }

    abstract protected function applyDirective(Directive $directive, $value);
}
