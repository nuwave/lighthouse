<?php

namespace Nuwave\Lighthouse\Schema\Directives;

use Closure;
use Illuminate\Support\Arr;
use Nuwave\Lighthouse\Schema\Values\FieldValue;
use Nuwave\Lighthouse\Support\Contracts\DefinedDirective;
use Nuwave\Lighthouse\Support\Contracts\FieldMiddleware;
use Nuwave\Lighthouse\Support\Utils;

class ComplexityDirective extends BaseDirective implements FieldMiddleware, DefinedDirective
{
    public static function definition(): string
    {
        return /** @lang GraphQL */ <<<'SDL'
"""
Customize the calculation of a fields complexity score before execution.
"""
directive @complexity(
  """
  Reference a function to customize the complexity score calculation.
  Consists of two parts: a class name and a method name, seperated by an `@` symbol.
  If you pass only a class name, the method name defaults to `__invoke`.
  """
  resolver: String
) on FIELD_DEFINITION
SDL;
    }

    /**
     * Resolve the field directive.
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
                /** @var int $complexity */
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
