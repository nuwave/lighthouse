<?php

namespace Nuwave\Lighthouse\Schema\Directives;

use Illuminate\Database\Eloquent\Model;
use Nuwave\Lighthouse\Exceptions\DefinitionException;
use Nuwave\Lighthouse\Schema\Values\FieldValue;
use Nuwave\Lighthouse\Support\Contracts\FieldResolver;
use function is_string;

class SumDirective extends BaseDirective implements FieldResolver
{
    public static function definition(): string
    {
        return /** @lang GraphQL */ <<<'GRAPHQL'
"""
Returns the sum of column a given model.
"""
directive @sum(
  """
  The model to run the sum on.
  """
  model: String

  """
  The column to run the sum on.
  """
  column: String!

) on FIELD_DEFINITION
GRAPHQL;
    }

    public function resolveField(FieldValue $value): FieldValue
    {
        $model = $this->directiveArgValue('model');
        $column = $this->directiveArgValue('column');
        if (is_string($model)&&is_string($column)) {
            return $value->setResolver(
                function () use ( $column, $model): int {
                    $query = $this
                        ->namespaceModelClass($model)
                        ::query();

                    return $query->sum($column);
                }
            );
        }

        if (is_string($column)) {
            return $value->setResolver(
                function (Model $parent) use ( $column ) : int {
                    return $parent->sum($column);
                }
            );
        }

        throw new DefinitionException(
            "A `model` argument must be assigned to the '{$this->name()}' directive on '{$this->nodeName()}'."
        );
    }
}
