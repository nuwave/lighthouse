<?php declare(strict_types=1);

namespace Nuwave\Lighthouse\Auth;

use Nuwave\Lighthouse\Execution\ResolveInfo;
use Nuwave\Lighthouse\Support\Contracts\GraphQLContext;

class CanModelDirective extends BaseCanDirective
{
    public static function definition(): string
    {
        $commonArguments = BaseCanDirective::commonArguments();

        return /** @lang GraphQL */ <<<GRAPHQL
"""
Check a Laravel Policy to ensure the current user is authorized to access a field.

Check the policy against the root model.
"""
directive @canModel(
{$commonArguments}

  """
  The model name to check against.
  """
  model: String

) repeatable on FIELD_DEFINITION
GRAPHQL;
    }

    protected function authorizeRequest(mixed $root, array $args, GraphQLContext $context, ResolveInfo $resolveInfo, callable $resolver, callable $authorize): mixed
    {
        $authorize($this->getModelClass());

        return $resolver($root, $args, $context, $resolveInfo);
    }
}
