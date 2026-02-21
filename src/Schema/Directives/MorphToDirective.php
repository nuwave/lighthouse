<?php declare(strict_types=1);

namespace Nuwave\Lighthouse\Schema\Directives;

use Closure;
use GraphQL\Type\Definition\ResolveInfo;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class MorphToDirective extends RelationDirective
{
    public static function definition(): string
    {
        return /** @lang GraphQL */ <<<'GRAPHQL'
"""
Corresponds to [Eloquent's MorphTo-Relationship](https://laravel.com/docs/eloquent-relationships#one-to-one-polymorphic-relations).
"""
directive @morphTo(
  """
  Specify the relationship method name in the model class,
  if it is named different from the field in the schema.
  """
  relation: String

  """
  Apply scopes to the underlying query.
  """
  scopes: [MorphToScopes!]
) on FIELD_DEFINITION

"""
Options for the `scopes` argument on `@morphTo`.
"""
input MorphToScopes {
    """
    Base or full class name of the related model the scope applies to.
    """
    model: String!

    """
    Names of the scopes to apply.
    """
    scopes: [String!]!
}
GRAPHQL;
    }

    protected function scopes(): array
    {
        return [];
    }

    protected function makeBuilderDecorator(ResolveInfo $resolveInfo): Closure
    {
        return function (object $builder) use ($resolveInfo) {
            (parent::makeBuilderDecorator($resolveInfo))($builder);

            $scopes = [];
            foreach ($this->directiveArgValue('scopes') ?? [] as $scopesForModel) {
                $scopes[$this->namespaceModelClass($scopesForModel['model'])] = function (Builder $builder) use ($scopesForModel): void {
                    foreach ($scopesForModel['scopes'] as $scope) {
                        $builder->{$scope}();
                    }
                };
            }

            assert($builder instanceof MorphTo);
            $builder->constrain($scopes);
        };
    }

    /**
     * @param  array<string, mixed>  $args
     *
     * @return array<int, int|string>
     */
    protected function qualifyPath(array $args, ResolveInfo $resolveInfo): array
    {
        // Includes the field we are loading the relation for
        $path = $resolveInfo->path;

        // In case we have no args, we can combine eager loads that are the same
        if ([] === $args) {
            array_pop($path);
        }

        // Each relation must be loaded separately
        $path[] = $this->relation();

        $scopes = [];
        foreach ($this->directiveArgValue('scopes') ?? [] as $scopesForModel) {
            $scopes[] = $scopesForModel['model'];
            foreach ($scopesForModel['scopes'] as $scope) {
                $scopes[] = $scope;
            }
        }

        // Scopes influence the result of the query
        return array_merge($path, $scopes);
    }
}
