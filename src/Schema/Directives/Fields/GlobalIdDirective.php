<?php

namespace Nuwave\Lighthouse\Schema\Directives\Fields;

use Nuwave\Lighthouse\Execution\Utils\GlobalId;
use Nuwave\Lighthouse\Schema\Values\FieldValue;
use Nuwave\Lighthouse\Schema\Directives\BaseDirective;
use Nuwave\Lighthouse\Support\Contracts\FieldMiddleware;

class GlobalIdDirective extends BaseDirective implements FieldMiddleware
{
    /**
     * Name of the directive.
     *
     * @return string
     */
    public function name()
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
    public function handleField(FieldValue $value, \Closure $next)
    {
        $type = $value->getNodeName();
        $resolver = $value->getResolver();

        return $next(
            $value->setResolver(
                function () use ($resolver, $type) {
                    $resolvedValue = call_user_func_array($resolver, func_get_args());

                    return 'encode' === $this->directiveArgValue('process', 'encode')
                        ? GlobalId::encode($type, $resolvedValue)
                        : GlobalId::decode($resolvedValue);
                }
            )
        );
    }
}
