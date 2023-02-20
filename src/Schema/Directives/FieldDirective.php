<?php

namespace Nuwave\Lighthouse\Schema\Directives;

use GraphQL\Type\Definition\ResolveInfo;
use Nuwave\Lighthouse\Schema\RootType;
use Nuwave\Lighthouse\Schema\Values\FieldValue;
use Nuwave\Lighthouse\Support\Contracts\FieldResolver;
use Nuwave\Lighthouse\Support\Contracts\GraphQLContext;
use Nuwave\Lighthouse\Support\Utils;

class FieldDirective extends BaseDirective implements FieldResolver
{
    public static function definition(): string
    {
        return /** @lang GraphQL */ <<<'GRAPHQL'
"""
Assign a resolver function to a field.
"""
directive @field(
  """
  A reference to the resolver function to be used.
  Consists of two parts: a class name and a method name, seperated by an `@` symbol.
  If you pass only a class name, the method name defaults to `__invoke`.
  """
  resolver: String!

  """
  Supply additional data to the resolver.
  """
  args: [String!]
) on FIELD_DEFINITION
GRAPHQL;
    }

    public function resolveField(FieldValue $fieldValue): FieldValue
    {
        [$className, $methodName] = $this->getMethodArgumentParts('resolver');

        $namespacedClassName = $this->namespaceClassName(
            $className,
            RootType::defaultNamespaces($fieldValue->getParentName())
        );

        $resolver = Utils::constructResolver($namespacedClassName, $methodName);

        $additionalData = $this->directiveArgValue('args');

        $fieldValue->setResolver(function ($root, array $args, GraphQLContext $context, ResolveInfo $resolveInfo) use ($resolver, $additionalData) {
            return $resolver(
                $root,
                array_merge($args, ['directive' => $additionalData]),
                $context,
                $resolveInfo
            );
        });

        return $fieldValue;
    }
}
