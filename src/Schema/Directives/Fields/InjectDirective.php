<?php

namespace Nuwave\Lighthouse\Schema\Directives\Fields;

use Closure;
use Illuminate\Support\Arr;
use GraphQL\Type\Definition\ResolveInfo;
use Nuwave\Lighthouse\Schema\Values\FieldValue;
use Nuwave\Lighthouse\Exceptions\DirectiveException;
use Nuwave\Lighthouse\Schema\Directives\BaseDirective;
use Nuwave\Lighthouse\Support\Contracts\GraphQLContext;
use Nuwave\Lighthouse\Support\Contracts\FieldMiddleware;

class InjectDirective extends BaseDirective implements FieldMiddleware
{
    /**
     * Name of the directive.
     *
     * @return string
     */
    public function name(): string
    {
        return 'inject';
    }

    /**
     * Resolve the field directive.
     *
     * @param  \Nuwave\Lighthouse\Schema\Values\FieldValue  $value
     * @param  \Closure  $next
     * @return \Nuwave\Lighthouse\Schema\Values\FieldValue
     *
     * @throws \Nuwave\Lighthouse\Exceptions\DirectiveException
     */
    public function handleField(FieldValue $value, Closure $next): FieldValue
    {
        $contextAttributeName = $this->directiveArgValue('context');
        if (! $contextAttributeName) {
            throw new DirectiveException(
                "The `inject` directive on {$value->getParentName()} [{$value->getFieldName()}] must have a `context` argument"
            );
        }

        $argumentName = $this->directiveArgValue('name');
        if (! $argumentName) {
            throw new DirectiveException(
                "The `inject` directive on {$value->getParentName()} [{$value->getFieldName()}] must have a `name` argument"
            );
        }

        $previousResolver = $value->getResolver();

        return $next(
            $value->setResolver(
                function ($rootValue, array $args, GraphQLContext $context, ResolveInfo $resolveInfo) use ($contextAttributeName, $argumentName, $previousResolver) {
                    return $previousResolver(
                        $rootValue,
                        Arr::add($args, $argumentName, data_get($context, $contextAttributeName)),
                        $context,
                        $resolveInfo
                    );
                }
            )
        );
    }
}
