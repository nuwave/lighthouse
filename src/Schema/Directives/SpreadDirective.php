<?php

namespace Nuwave\Lighthouse\Schema\Directives;

use Closure;
use GraphQL\Type\Definition\ResolveInfo;
use Nuwave\Lighthouse\Execution\Arguments\ArgumentSet;
use Nuwave\Lighthouse\Schema\Values\FieldValue;
use Nuwave\Lighthouse\Support\Contracts\FieldMiddleware;
use Nuwave\Lighthouse\Support\Contracts\GraphQLContext;
use Nuwave\Lighthouse\Support\Utils;

class SpreadDirective extends BaseDirective implements FieldMiddleware
{
    public static function definition(): string
    {
        return /** @lang GraphQL */ <<<'GRAPHQL'
"""
Merge the fields of a nested input object into the arguments of its parent
when processing the field arguments given by a client.
"""
directive @spread on ARGUMENT_DEFINITION | INPUT_FIELD_DEFINITION
GRAPHQL;
    }

    public function handleField(FieldValue $fieldValue, Closure $next)
    {
        $resolver = $fieldValue->getResolver();

        return $next(
            $fieldValue->setResolver(
                function ($root, array $args, GraphQLContext $context, ResolveInfo $resolveInfo) use ($resolver) {
                    $resolveInfo->argumentSet = $this->spread($resolveInfo->argumentSet);

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

    /**
     * Apply the @spread directive and return a new, modified ArgumentSet.
     *
     * @noRector \Rector\DeadCode\Rector\ClassMethod\RemoveDeadRecursiveClassMethodRector
     */
    protected static function spread(ArgumentSet $original): ArgumentSet
    {
        $next = new ArgumentSet();
        $next->directives = $original->directives;

        foreach ($original->arguments as $name => $argument) {
            $value = $argument->value;

            // In this case, we do not care about argument sets nested within
            // lists, spreading only makes sense for single nested inputs.
            if ($value instanceof ArgumentSet) {
                // Recurse down first, as that resolves the more deeply nested spreads first
                $value = self::spread($value);

                if ($argument->directives->contains(
                    Utils::instanceofMatcher(static::class)
                )) {
                    $next->arguments += $value->arguments;
                    continue;
                }
            }

            $next->arguments[$name] = $argument;
        }

        return $next;
    }
}
