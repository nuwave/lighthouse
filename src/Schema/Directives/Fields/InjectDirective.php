<?php

namespace Nuwave\Lighthouse\Schema\Directives\Fields;

use Nuwave\Lighthouse\Schema\Values\FieldValue;
use Nuwave\Lighthouse\Support\Exceptions\DirectiveException;

class InjectDirective extends AbstractFieldDirective implements FieldMiddleware
{
    /**
     * Name of the directive.
     *
     * @return string
     */
    public static function name()
    {
        return 'inject';
    }

    /**
     * Resolve the field directive.
     *
     * @param FieldValue $value
     *
     * @throws DirectiveException
     *
     * @return \Closure
     */
    public function handleField(FieldValue $value)
    {
        $resolver = $value->getResolver();
        $attr = $this->associatedArgValue('context');

        $name = $this->associatedArgValue('name');

        if (! $attr) {
            throw new DirectiveException(sprintf(
                'The `%s` directive on %s [%s] must have a `context` argument',
                self::name(),
                $value->getParentTypeName(),
                $value->getFieldName()
            ));
        }

        if (! $name) {
            throw new DirectiveException(sprintf(
                'The `%s` directive on %s [%s] must have a `name` argument',
                self::name(),
                $value->getParentTypeName(),
                $value->getFieldName()
            ));
        }

        return function () use ($attr, $name, $resolver) {
            $args = func_get_args();
            $context = $args[2];
            $args[1] = array_merge($args[1], [$name => data_get($context, $attr)]);

            return call_user_func_array($resolver, $args);
        };
    }
}
