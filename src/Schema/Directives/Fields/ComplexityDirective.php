<?php

namespace Nuwave\Lighthouse\Schema\Directives\Fields;

use Illuminate\Support\Arr;
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
            [$className, $methodName] = $this->getMethodArgumentParts('resolver');

            $namespacedClassName = $this->namespaceClassName(
                $className,
                $fieldValue->defaultNamespacesForParent()
            );

            $resolver = construct_resolver($namespacedClassName, $methodName);
        } else {
            $resolver = function (int $childrenComplexity, array $args): int {
                $complexity = Arr::get(
                    $args,
                    'first',
                    Arr::get($args, 'count', 1)
                );

                return $childrenComplexity * $complexity;
            };
        }

        return $next(
            $fieldValue->setComplexity($resolver)
        );
    }
}
