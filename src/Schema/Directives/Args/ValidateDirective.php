<?php

namespace Nuwave\Lighthouse\Schema\Directives\Args;

use Nuwave\Lighthouse\Schema\Values\FieldValue;
use Nuwave\Lighthouse\Schema\Values\ArgumentValue;
use Nuwave\Lighthouse\Schema\Directives\BaseDirective;
use Nuwave\Lighthouse\Support\Contracts\ArgMiddleware;
use Nuwave\Lighthouse\Support\Contracts\FieldMiddleware;
use Nuwave\Lighthouse\Support\Exceptions\DirectiveException;

class ValidateDirective extends BaseDirective implements ArgMiddleware, FieldMiddleware
{
    /**
     * Directive name.
     *
     * @return string
     */
    public function name()
    {
        return 'validate';
    }

    /**
     * Resolve the field directive.
     *
     * @param FieldValue $value
     * @param \Closure    $next
     *
     * @throws DirectiveException
     *
     * @return FieldValue
     */
    public function handleField(FieldValue $value, \Closure $next)
    {
        $validator = $this->directiveArgValue('validator');

        if (! $validator) {
            $fieldName = $value->getFieldName();
            $message = "A `validator` argument must be supplied on the @validate directive on field {$fieldName}";

            throw new DirectiveException($message);
        }

        $resolver = $value->getResolver();

        return $next($value->setResolver(function () use ($validator, $resolver) {
            $funcArgs = func_get_args();
            $root = array_get($funcArgs, '0');
            $args = array_get($funcArgs, '1');
            $context = array_get($funcArgs, '2');
            $info = array_get($funcArgs, '3');

            app($validator, compact('root', 'args', 'context', 'info'))->validate();

            return call_user_func_array($resolver, $funcArgs);
        }));
    }

    /**
     * Resolve the field directive.
     *
     * @param ArgumentValue $value
     * @param \Closure       $next
     *
     * @return ArgumentValue
     */
    public function handleArgument(ArgumentValue $value, \Closure $next)
    {
        $rules = $this->directiveArgValue('rules', []);

        $current = $value->getValue();
        $current['rules'] = array_merge(
            array_get($value->getArg(), 'rules', []),
            $rules
        );

        return $next($value->setValue($current));
    }
}
