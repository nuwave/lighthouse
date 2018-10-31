<?php

namespace Nuwave\Lighthouse\Schema\Directives\Fields;

use Nuwave\Lighthouse\Schema\Values\FieldValue;
use Nuwave\Lighthouse\Exceptions\DirectiveException;
use Nuwave\Lighthouse\Exceptions\DefinitionException;
use Nuwave\Lighthouse\Schema\Directives\BaseDirective;
use Nuwave\Lighthouse\Support\Contracts\FieldResolver;

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
     * @param FieldValue $fieldValue
     *
     * @throws DirectiveException
     * @throws DefinitionException
     *
     * @return FieldValue
     */
    public function resolveField(FieldValue $fieldValue): FieldValue
    {
        if($this->directiveHasArgument('resolver')){
            list($className, $methodName) = $this->getMethodArgumentParts('resolver');
        } else {
            /**
             * @deprecated This behaviour will be removed in v3
             *
             * The only way to define methods will be via the resolver: "Class@method" style
             */
            $className = $this->directiveArgValue('class');
            $methodName = $this->directiveArgValue('method');
        }

        if($parentNamespace = $fieldValue->getDefaultNamespaceForParent()){
            $namespacedClassName = $this->namespaceClassName($className, [$parentNamespace]);
        } else {
            $namespacedClassName = $this->namespaceClassName($className);
        }

        $resolver = construct_resolver($namespacedClassName, $methodName);

        $additionalData = $this->directiveArgValue('args');

        return $fieldValue->setResolver(
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
