<?php

namespace Nuwave\Lighthouse\Schema\Directives;

use GraphQL\Type\Definition\ResolveInfo;
use Illuminate\Database\Eloquent\Collection;
use Nuwave\Lighthouse\Schema\Values\FieldValue;
use Nuwave\Lighthouse\Support\Contracts\FieldResolver;
use Nuwave\Lighthouse\Support\Contracts\GraphQLContext;

class AllDirective extends BaseDirective implements FieldResolver
{
    public static function definition(): string
    {
        return /** @lang GraphQL */ <<<'GRAPHQL'
"""
Fetch all Eloquent models and return the collection as the result.
"""
directive @all(
  """
  Specify the class name of the model to use.
  This is only needed when the default model detection does not work.
  """
  model: String

  """
  Point to a function that provides a Query Builder instance.
  This replaces the use of a model.
  """
  builder: String

  """
  Apply scopes to the underlying query.
  """
  scopes: [String!]
) on FIELD_DEFINITION
GRAPHQL;
    }

    public function resolveField(FieldValue $fieldValue): FieldValue
    {
        return $fieldValue->setResolver(
            function ($root, array $args, GraphQLContext $context, ResolveInfo $resolveInfo): Collection {
                if ($this->directiveHasArgument('builder')) {
                    $builderResolver = $this->getResolverFromArgument('builder');

                    $query = $builderResolver($root, $args, $context, $resolveInfo);
                } else {
                    $query = $this->getModelClass()::query();
                }

                return $resolveInfo
                    ->argumentSet
                    ->enhanceBuilder(
                        $query,
                        $this->directiveArgValue('scopes', [])
                    )
                    ->get();
            }
        );
    }
}
