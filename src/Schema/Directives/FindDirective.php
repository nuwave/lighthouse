<?php declare(strict_types=1);

namespace Nuwave\Lighthouse\Schema\Directives;

use GraphQL\Error\Error;
use Illuminate\Database\Eloquent\Model;
use Nuwave\Lighthouse\Execution\ResolveInfo;
use Nuwave\Lighthouse\Schema\Values\FieldValue;
use Nuwave\Lighthouse\Support\Contracts\FieldResolver;
use Nuwave\Lighthouse\Support\Contracts\GraphQLContext;

class FindDirective extends BaseDirective implements FieldResolver
{
    public static function definition(): string
    {
        return /** @lang GraphQL */ <<<'GRAPHQL'
"""
Find a model based on the arguments provided.
"""
directive @find(
  """
  Specify the class name of the model to use.
  This is only needed when the default model detection does not work.
  """
  model: String

  """
  Apply scopes to the underlying query.
  """
  scopes: [String!]
) on FIELD_DEFINITION
GRAPHQL;
    }

    public function resolveField(FieldValue $fieldValue): callable
    {
        return function (mixed $root, array $args, GraphQLContext $context, ResolveInfo $resolveInfo): ?Model {
            $results = $resolveInfo
                ->enhanceBuilder(
                    $this->getModelClass()::query(),
                    $this->directiveArgValue('scopes', []),
                    $root,
                    $args,
                    $context,
                    $resolveInfo,
                )
                ->get();

            if ($results->count() > 1) {
                throw new Error('The query returned more than one result.');
            }

            return $results->first();
        };
    }
}
