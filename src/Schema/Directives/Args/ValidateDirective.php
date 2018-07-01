<?php

namespace Nuwave\Lighthouse\Schema\Directives\Args;

use GraphQL\Language\AST\ArgumentNode;
use GraphQL\Language\AST\DirectiveNode;
use Nuwave\Lighthouse\Schema\Directives\BaseDirective;
use Nuwave\Lighthouse\Schema\Values\ArgumentValue;
use Nuwave\Lighthouse\Schema\Values\FieldValue;
use Nuwave\Lighthouse\Support\Contracts\ArgMiddleware;
use Nuwave\Lighthouse\Support\Contracts\FieldMiddleware;
use Nuwave\Lighthouse\Support\Exceptions\DirectiveException;

class ValidateDirective extends BaseDirective implements FieldMiddleware
{
    /**
     * Directive name.
     *
     * @return string
     */
    public function name(): string
    {
        return 'validate';
    }

    /**
     * Resolve the field directive.
     *
     * @param FieldValue $value
     *
     * @return FieldValue
     * @throws DirectiveException
     */
    public function handleField(FieldValue $value): FieldValue
    {
        $validator = $this->directiveArgValue('validator');

        if (! $validator) {
            $fieldName = $value->getFieldName();
            $message = "A `validator` argument must be supplied on the @validate directive on field {$fieldName}";

            throw new DirectiveException($message);
        }

        $resolver = $value->getResolver();

        return $value->setResolver(function () use ($validator, $resolver) {
            $funcArgs = func_get_args();
            $root = array_get($funcArgs, '0');
            $args = array_get($funcArgs, '1');
            $context = array_get($funcArgs, '2');
            $info = array_get($funcArgs, '3');

            // The array keys are named for more convenient retrieval in custom validator classes
            app($validator, compact('root', 'args', 'context', 'info'))->validate();

            return call_user_func_array($resolver, $funcArgs);
        });
    }
}
