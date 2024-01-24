<?php declare(strict_types=1);

namespace Nuwave\Lighthouse\Schema\Directives;

use Nuwave\Lighthouse\Schema\Values\FieldValue;
use Nuwave\Lighthouse\Support\Contracts\ComplexityResolverDirective;
use Nuwave\Lighthouse\Support\Utils;

class ComplexityDirective extends BaseDirective implements ComplexityResolverDirective
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
  resolver: String!
) on FIELD_DEFINITION
GRAPHQL;
    }

    public function complexityResolver(FieldValue $fieldValue): callable
    {
        $resolver = $this->directiveArgValue('resolver');
        assert(is_string($resolver));

        [$className, $methodName] = $this->getMethodArgumentParts('resolver');

        $namespacedClassName = $this->namespaceClassName(
            $className,
            $fieldValue->parentNamespaces(),
        );

        return Utils::constructResolver($namespacedClassName, $methodName);
    }

    /** @param  array<string, mixed>  $args */
    public static function defaultComplexityResolver(int $childrenComplexity, array $args): int
    {
        /**
         * Assuming pagination, @see PaginationManipulator::countArgument().
         */
        $first = $args['first'] ?? null;

        $expectedNumberOfChildren = is_int($first)
            ? $first
            : 1;

        return
            // Default complexity for this field itself
            1
            // Scale children complexity by the expected number of results
            + $childrenComplexity * $expectedNumberOfChildren;
    }
}
