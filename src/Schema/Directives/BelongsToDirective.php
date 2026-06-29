<?php declare(strict_types=1);

namespace Nuwave\Lighthouse\Schema\Directives;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Nuwave\Lighthouse\Execution\Arguments\NestedBelongsTo;
use Nuwave\Lighthouse\Execution\Arguments\ResolveNested;
use Nuwave\Lighthouse\Support\Contracts\PreSaveArgResolver;

class BelongsToDirective extends RelationDirective implements PreSaveArgResolver
{
    public static function definition(): string
    {
        return /** @lang GraphQL */ <<<'GRAPHQL'
"""
Resolves a field through the Eloquent `BelongsTo` relationship.
When used on an input field, handles nested mutations for the BelongsTo relationship.
"""
directive @belongsTo(
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
        assert($root instanceof Model);
        $relationName = $this->directiveArgValue('relation') ?? $this->nodeName();
        $relation = $root->{$relationName}();
        assert($relation instanceof BelongsTo);
        (new ResolveNested(new NestedBelongsTo($relation)))($root, $value);
    }
}
