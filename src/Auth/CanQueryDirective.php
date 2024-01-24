<?php declare(strict_types=1);

namespace Nuwave\Lighthouse\Auth;

use Nuwave\Lighthouse\Execution\ResolveInfo;
use Nuwave\Lighthouse\Support\Contracts\GraphQLContext;

class CanQueryDirective extends BaseCanDirective
{
    public static function definition(): string
    {
        $commonArguments = BaseCanDirective::commonArguments();

        return /** @lang GraphQL */ <<<GRAPHQL
"""
Check a Laravel Policy to ensure the current user is authorized to access a field.

Query for specific model instances to check the policy against, using arguments
with directives that add constraints to the query builder, such as `@eq`.
"""
directive @canQuery(
{$commonArguments}

  """
  Apply scopes to the underlying query.
  """
  scopes: [String!]
) repeatable on FIELD_DEFINITION
GRAPHQL;
    }

    protected function authorizeRequest(mixed $root, array $args, GraphQLContext $context, ResolveInfo $resolveInfo, callable $resolver, callable $authorize): mixed
    {
        $models = $resolveInfo
            ->enhanceBuilder(
                $this->getModelClass()::query(),
                $this->directiveArgValue('scopes', []),
                $root,
                $args,
                $context,
                $resolveInfo,
            )
            ->get();
        foreach ($models as $model) {
            $authorize($model);
        }

        return $resolver($root, $args, $context, $resolveInfo);
    }
}
