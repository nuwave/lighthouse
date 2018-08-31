<?php

namespace Nuwave\Lighthouse\Schema\Directives\Fields;

use GraphQL\Type\Definition\ResolveInfo;
use Nuwave\Lighthouse\Schema\Values\FieldValue;
use Nuwave\Lighthouse\Schema\Directives\BaseDirective;
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
     * @param \Closure $next
     *
     * @throws DirectiveException
     *
     * @return FieldValue
     */
    public function handleField(FieldValue $value, \Closure $next)
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

        return $next(
            $value->setResolver(
                function ($rootValue, $args, $context, ResolveInfo $resolveInfo) use ($attr, $name, $resolver) {
                    return call_user_func_array($resolver, [
                        $rootValue,
                        array_merge($args, [$name => data_get($context, $attr)]),
                        $context,
                        $resolveInfo
                    ]);
                }
            )
        );
    }
}
