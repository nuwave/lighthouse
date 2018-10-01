<?php

namespace Nuwave\Lighthouse\Schema\Directives\Fields;

use Nuwave\Lighthouse\Schema\Values\FieldValue;
use Nuwave\Lighthouse\Schema\Directives\BaseDirective;
use Nuwave\Lighthouse\Support\Contracts\FieldResolver;
use Nuwave\Lighthouse\Exceptions\DirectiveException;

class FieldDirective extends BaseDirective implements FieldResolver
{
    /**
     * Name of the directive.
     *
     * @return string
     */
    public function name(): string
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
    public function resolveField(FieldValue $value): FieldValue
    {
        if($this->directiveHasArgument('resolver')){
            $resolver = $this->getMethodArgument('resolver');
        } else {
            /**
             * @deprecated This behaviour will be removed in v3
             *
             * The only way to define methods will be via the resolver: "Class@method" style
             */
            $className = $this->namespaceClassName(
                $this->directiveArgValue('class')
            );
            $methodName = $this->directiveArgValue('method');
            if (! method_exists($className, $methodName)) {
                throw new DirectiveException("Method '{$methodName}' does not exist on class '{$className}'");
            }
    
            // TODO convert this back once we require PHP 7.1
            // $resolver = \Closure::fromCallable([resolve($className), $methodName]);
            $resolver = function() use ($className, $methodName){
                return resolve($className)->{$methodName}(...func_get_args());
            };
        }

        $additionalData = $this->directiveArgValue('args');

        return $value->setResolver(
            function ($root, array $args, $context = null, $info = null) use ($resolver, $additionalData) {
                return $resolver(
                    $root,
                    array_merge($args, ['directive' => $additionalData]),
                    $context,
                    $info
                );
            }
        );
    }
}
