<?php

namespace Nuwave\Lighthouse\Schema\Directives\Fields;

use Nuwave\Lighthouse\Schema\Values\FieldValue;
use Nuwave\Lighthouse\Exceptions\DirectiveException;
use Nuwave\Lighthouse\Exceptions\DefinitionException;
use Nuwave\Lighthouse\Schema\Directives\BaseDirective;
use Nuwave\Lighthouse\Support\Contracts\FieldMiddleware;

class ComplexityDirective extends BaseDirective implements FieldMiddleware
{
    /**
     * Name of the directive.
     *
     * @return string
     */
    public function name(): string
    {
        return 'complexity';
    }

    /**
     * Resolve the field directive.
     *
     * @param FieldValue $fieldValue
     * @param \Closure   $next
     *
     * @throws DirectiveException
     * @throws DefinitionException
     *
     * @return FieldValue
     */
    public function handleField(FieldValue $fieldValue, \Closure $next): FieldValue
    {
        if ($this->directiveHasArgument('resolver')) {
            list($className, $methodName) = $this->getMethodArgumentParts('resolver');

            if ($parentNamespace = $fieldValue->getDefaultNamespaceForParent()) {
                $namespacedClassName = $this->namespaceClassName($className, [$parentNamespace]);
            } else {
                $namespacedClassName = $this->namespaceClassName($className);
            }

            $resolver = construct_resolver($namespacedClassName, $methodName);
        } else {
            $resolver = function (int $childrenComplexity, array $args): int {
                $complexity = array_get(
                    $args,
                    'first',
                    array_get($args, 'count', 1)
                );

                return $childrenComplexity * $complexity;
            };
        }

        return $next(
            $fieldValue->setComplexity($resolver)
        );
    }
}
