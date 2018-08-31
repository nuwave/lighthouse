<?php

namespace Nuwave\Lighthouse\Schema\Directives\Args;

use GraphQL\Type\Definition\ResolveInfo;
use Nuwave\Lighthouse\Schema\Values\FieldValue;
use Nuwave\Lighthouse\Execution\GraphQLValidator;
use Nuwave\Lighthouse\Schema\Values\ArgumentValue;
use Nuwave\Lighthouse\Support\Contracts\ArgMiddleware;
use Nuwave\Lighthouse\Schema\Directives\BaseDirective;
use Nuwave\Lighthouse\Support\Contracts\FieldMiddleware;
use Nuwave\Lighthouse\Exceptions\DirectiveException;

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
     * @param \Closure $next
     *
     * @throws DirectiveException
     *
     * @return FieldValue
     */
    public function handleField(FieldValue $value, \Closure $next)
    {
        $resolver = $value->getResolver();

        return $next(
            $value->setResolver(
                function ($root, $args, $context, ResolveInfo $resolveInfo) use ($resolver) {
                    $validatorName = $this->directiveArgValue('validator');
                    $fieldName = $resolveInfo->fieldName;

                    if (!$validatorName) {
                        throw new DirectiveException("A `validator` argument must be supplied on the @validate directive on field {$fieldName}");
                    }

                    $validator = app($validatorName, [
                        'data' => $args,
                        'rules' => [],
                        'customAttributes' => [
                            'root' => $root,
                            'context' => $context,
                            'resolveInfo' => $resolveInfo
                        ]
                    ]);

                    if (!$validator instanceof GraphQLValidator) {
                        throw new DirectiveException("The validator on field {$fieldName} must extend the GraphQLValidator class.");
                    }

                    return call_user_func_array($resolver, func_get_args());
                }
            )
        );
    }

    /**
     * Resolve the field directive.
     *
     * @param ArgumentValue $value
     * @param \Closure $next
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
