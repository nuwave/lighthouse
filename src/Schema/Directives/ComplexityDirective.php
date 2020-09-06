<?php

namespace Nuwave\Lighthouse\Schema\Directives;

use Closure;
use Illuminate\Support\Arr;
use Nuwave\Lighthouse\Schema\Values\FieldValue;
use Nuwave\Lighthouse\Support\Contracts\FieldMiddleware;
use Nuwave\Lighthouse\Support\Utils;

class ComplexityDirective extends BaseDirective implements FieldMiddleware
{
    public static function definition(): string
    {
        return /** @lang GraphQL */ <<<'GRAPHQL'
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
GRAPHQL;
    }

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
                $complexity = Arr::get($args, 'first', 1);

                return $childrenComplexity * $complexity;
            };
        }

        return $next(
            $fieldFieldValue->setComplexity($resolver)
        );
    }
}
