<?php

namespace Nuwave\Lighthouse\Schema\Directives;

use Closure;
use Illuminate\Support\Arr;
use Nuwave\Lighthouse\Support\Utils;
use Nuwave\Lighthouse\Schema\Values\FieldValue;
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
     * @param  \Nuwave\Lighthouse\Schema\Values\FieldValue  $fieldFieldValue
     * @param  \Closure  $next
     * @return \Nuwave\Lighthouse\Schema\Values\FieldValue
     */
    public function handleField(FieldValue $fieldFieldValue, Closure $next): FieldValue
    {
        if ($this->directiveHasArgument('resolver')) {
            [$className, $methodName] = $this->getMethodArgumentParts('resolver');

            $namespacedClassName = $this->namespaceClassName(
                $className,
                $fieldFieldValue->defaultNamespacesForParent()
            );

            $resolver = Utils::constructResolver($namespacedClassName, $methodName);
        } else {
            $resolver = function (int $childrenComplexity, array $args): int {
                $complexity = Arr::get(
                    $args,
                    'first',
                    Arr::get($args, config('lighthouse.pagination_amount_argument'), 1)
                );

                return $childrenComplexity * $complexity;
            };
        }

        return $next(
            $fieldFieldValue->setComplexity($resolver)
        );
    }
}
