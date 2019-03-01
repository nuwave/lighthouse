<?php

namespace Nuwave\Lighthouse\Schema\Directives\Fields;

use Closure;
use Nuwave\Lighthouse\Execution\Utils\GlobalId;
use Nuwave\Lighthouse\Schema\Values\FieldValue;
use Nuwave\Lighthouse\Schema\Directives\BaseDirective;
use Nuwave\Lighthouse\Support\Contracts\FieldMiddleware;
use Nuwave\Lighthouse\Support\Contracts\ArgTransformerDirective;

class GlobalIdDirective extends BaseDirective implements FieldMiddleware, ArgTransformerDirective
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
     * @param  \Nuwave\Lighthouse\Schema\Values\FieldValue  $value
     * @param  \Closure  $next
     *
     * @return \Nuwave\Lighthouse\Schema\Values\FieldValue
     */
    public function handleField(FieldValue $value, Closure $next): FieldValue
    {
        $type = $value->getParentName();
        $resolver = $value->getResolver();

        return $next(
            $value->setResolver(
                function () use ($type, $resolver) {
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
     * Return an array containing the type name and id.
     *
     * @param  string  $argumentValue
     *
     * @return string[]
     */
    public function transform($argumentValue): array
    {
        return GlobalId::decode($argumentValue);
    }
}
