<?php

namespace Nuwave\Lighthouse\Schema\Directives\Fields;

use GraphQL\Type\Definition\ResolveInfo;
use Nuwave\Lighthouse\Execution\Utils\GlobalId;
use Nuwave\Lighthouse\Schema\Values\FieldValue;
use Nuwave\Lighthouse\Schema\Values\ArgumentValue;
use Nuwave\Lighthouse\Schema\Directives\BaseDirective;
use Nuwave\Lighthouse\Support\Contracts\ArgMiddleware;
use Nuwave\Lighthouse\Support\Contracts\FieldMiddleware;

class GlobalIdDirective extends BaseDirective implements FieldMiddleware, ArgMiddleware
{
    /**
     * Name of the directive.
     *
     * @return string
     */
    public function name(): string
    {
        return 'globalId';
    }

    /**
     * Resolve the field directive.
     *
     * @param FieldValue $value
     * @param \Closure $next
     *
     * @return FieldValue
     */
    public function handleField(FieldValue $value, \Closure $next): FieldValue
    {
        $type = $value->getParentName();
        $resolver = $value->getResolver();

        return $next(
            $value->setResolver(
                function ($root, $args, $context, ResolveInfo $resolveInfo) use ($type, $resolver){
                    $resolvedValue = call_user_func_array($resolver, func_get_args());

                    return GlobalId::encode(
                        $type,
                        $resolvedValue
                    );
                }
            )
        );
    }

    /**
     * Apply transformations on the ArgumentValue.
     *
     * @param ArgumentValue $argument
     * @param \Closure $next
     *
     * @return ArgumentValue
     */
    public function handleArgument(ArgumentValue $argument, \Closure $next): ArgumentValue
    {
        return $next(
            $argument->addTransformer(
                function ($globalId) {
                    return GlobalId::decode($globalId);
                }
            )
        );
    }
}
