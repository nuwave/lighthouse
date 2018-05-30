<?php

namespace Nuwave\Lighthouse\Schema\Directives\Args;

use GraphQL\Language\AST\ArgumentNode;
use GraphQL\Language\AST\DirectiveNode;
use Nuwave\Lighthouse\Schema\Values\ArgumentValue;
use Nuwave\Lighthouse\Schema\Values\FieldValue;
use Nuwave\Lighthouse\Support\Contracts\ArgMiddleware;
use Nuwave\Lighthouse\Support\Contracts\FieldMiddleware;
use Nuwave\Lighthouse\Support\Exceptions\DirectiveException;
use Nuwave\Lighthouse\Support\Traits\HandlesDirectives;

class ValidateDirective implements ArgMiddleware, FieldMiddleware
{
    use HandlesDirectives;

    /**
     * Directive name.
     *
     * @return string
     */
    public static function name()
    {
        return 'validate';
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
        $validator = $this->directiveArgValue(
            $this->fieldDirective($value->getField(), self::name()),
            'validator'
        );

        if (! $validator) {
            $message = 'A `validator` argument must be supplied on the @validate field directive';

            throw new DirectiveException($message);
        }

        $resolver = $value->getResolver();

        return $value->setResolver(function () use ($validator, $resolver) {
            $funcArgs = func_get_args();
            $root = array_get($funcArgs, '0');
            $args = array_get($funcArgs, '1');
            $context = array_get($funcArgs, '2');
            $info = array_get($funcArgs, '3');

            app($validator, compact('root', 'args', 'context', 'info'))->validate();

            return call_user_func_array($resolver, $funcArgs);
        });
    }

    /**
     * Resolve the field directive.
     *
     * @param ArgumentValue $value
     *
     * @return array
     */
    public function handleArgument(ArgumentValue $value)
    {
        // TODO: Rename "getValue" to something more descriptive like "toArray"
        // and consider using for NodeValue/FieldValue.
        $current = $value->getValue();
        $current['rules'] = array_merge(
            array_get($value->getArg(), 'rules', []),
            $this->getRules($value->getDirective())
        );

        return $value->setValue($current);
    }

    /**
     * Get array of rules to apply to field.
     *
     * @param DirectiveNode $directive
     *
     * @return array
     */
    protected function getRules(DirectiveNode $directive)
    {
        return collect($directive->arguments)->map(function (ArgumentNode $arg) {
            return $this->argValue($arg);
        })->collapse()->toArray();
    }
}
