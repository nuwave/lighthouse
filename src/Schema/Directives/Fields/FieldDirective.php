<?php

namespace Nuwave\Lighthouse\Schema\Directives\Fields;

use Nuwave\Lighthouse\Schema\Directives\BaseDirective;
use Nuwave\Lighthouse\Schema\Values\FieldValue;
use Nuwave\Lighthouse\Support\Contracts\FieldResolver;
use Nuwave\Lighthouse\Support\Exceptions\DirectiveException;

class FieldDirective extends BaseDirective implements FieldResolver
{
    /**
     * Name of the directive.
     *
     * @return string
     */
    public function name()
    {
        return 'field';
    }

    /**
     * Resolve the field directive.
     *
     * @param FieldValue $value
     *
     * @throws DirectiveException
     *
     * @return FieldValue
     */
    public function resolveField(FieldValue $value)
    {
        $resolver = $this->getResolver();
        $resolverClass = $resolver->className();
        $resolverMethod = $resolver->methodName();
        $additionalData = $this->directiveArgValue('args');

        return $value->setResolver(
            function ($root, array $args, $context = null, $info = null) use ($resolverClass, $resolverMethod, $additionalData) {
                return call_user_func_array(
                    [app($resolverClass), $resolverMethod],
                    [$root, array_merge($args, ['directive' => $additionalData]), $context, $info]
                );
            }
        );
    }
}
