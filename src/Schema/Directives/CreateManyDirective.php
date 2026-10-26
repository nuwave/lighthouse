<?php declare(strict_types=1);

namespace Nuwave\Lighthouse\Schema\Directives;

use Illuminate\Database\Eloquent\Relations\Relation;
use Nuwave\Lighthouse\Execution\Arguments\SaveModel;

class CreateManyDirective extends ManyModelMutationDirective
{
    public static function definition(): string
    {
        return /** @lang GraphQL */ <<<'GRAPHQL'
"""
Create multiple new Eloquent models with the given arguments.
"""
directive @createMany(
  """
  Specify the class name of the model to use.
  This is only needed when the default model detection does not work.
  """
  model: String

  """
  Specify the name of the relation on the parent model.
  Only necessary when this directive is used as a nested arg resolver and the relation is named differently from the argument.
  """
  relation: String
) on FIELD_DEFINITION | ARGUMENT_DEFINITION | INPUT_FIELD_DEFINITION
GRAPHQL;
    }

    protected function makeExecutionFunction(?Relation $parentRelation = null): callable
    {
        return new SaveModel($parentRelation);
    }
}
