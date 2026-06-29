<?php declare(strict_types=1);

namespace Nuwave\Lighthouse\Schema\Directives;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Nuwave\Lighthouse\Execution\Arguments\NestedOneToOne;
use Nuwave\Lighthouse\Execution\Arguments\ResolveNested;
use Nuwave\Lighthouse\Support\Contracts\ArgResolver;

class HasOneDirective extends RelationDirective implements ArgResolver
{
    public static function definition(): string
    {
        return /** @lang GraphQL */ <<<'GRAPHQL'
"""
Corresponds to [the Eloquent relationship HasOne](https://laravel.com/docs/eloquent-relationships#one-to-one).
When used on an input field, handles nested mutations for the HasOne relationship.
"""
directive @hasOne(
  """
  Specify the relationship method name in the model class,
  if it is named different from the field in the schema.
  """
  relation: String

  """
  Apply scopes to the underlying query.
  """
  scopes: [String!]
) on FIELD_DEFINITION | INPUT_FIELD_DEFINITION
GRAPHQL;
    }

    public function __invoke(mixed $root, mixed $value): void
    {
        assert($root instanceof Model, 'HasOneDirective is only used as an ArgResolver on Eloquent models.');
        $relationName = $this->directiveArgValue('relation') ?? $this->nodeName();
        $relation = $root->{$relationName}();
        assert($relation instanceof HasOne, "Use @hasOne only for HasOne relations, not for: {$relationName}.");
        (new ResolveNested(new NestedOneToOne($relationName)))($root, $value);
    }
}
