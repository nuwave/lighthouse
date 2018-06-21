<?php

namespace Nuwave\Lighthouse\Schema\Directives\Fields;

use Nuwave\Lighthouse\Schema\Directives\BaseDirective;
use Nuwave\Lighthouse\Schema\Values\FieldValue;
use Nuwave\Lighthouse\Support\Contracts\FieldMiddleware;
use Nuwave\Lighthouse\Support\Exceptions\DirectiveException;

class InjectDirective extends BaseDirective implements FieldMiddleware
{
    /**
     * Name of the directive.
     *
     * @return string
     */
    public function name()
    {
        return 'inject';
    }

    /**
     * Resolve the field directive.
     *
     * @param FieldValue $value
     *
     * @return FieldValue
     */
    public function handleField(FieldValue $value)
    {
        $resolver = $value->getResolver();
        $attr = $this->directiveArgValue('context');
        $name = $this->directiveArgValue('name');

        if (!$attr) {
            throw new DirectiveException(sprintf(
                'The `inject` directive on %s [%s] must have a `context` argument',
                $value->getNodeName(),
                $value->getFieldName()
            ));
        }

        if (!$name) {
            throw new DirectiveException(sprintf(
                'The `inject` directive on %s [%s] must have a `name` argument',
                $value->getNodeName(),
                $value->getFieldName()
            ));
        }

        return $value->setResolver(function () use ($attr, $name, $resolver) {
            $args = func_get_args();
            $context = $args[2];
            $args[1] = array_merge($args[1], [$name => data_get($context, $attr)]);

            return call_user_func_array($resolver, $args);
        });
    }
}
