<?php

namespace Nuwave\Lighthouse\Schema\Directives;

use Illuminate\Database\Eloquent\Model;
use Nuwave\Lighthouse\Exceptions\DirectiveException;
use Nuwave\Lighthouse\Schema\Values\FieldValue;
use Nuwave\Lighthouse\Support\Contracts\DefinedDirective;
use Nuwave\Lighthouse\Support\Contracts\FieldResolver;

class CountDirective extends BaseDirective implements FieldResolver, DefinedDirective
{
    public static function definition(): string
    {
        return /** @lang GraphQL */ <<<'SDL'
"""
Returns the count of a given relationship or model.
"""
directive @count(
  """
  The relationship which you want to run the count on.
  """
  relation: String

  """
  The model to run the count on.
  """
  model: String
) on FIELD_DEFINITION
SDL;
    }

    /**
     * Returns the count of a given relationship or model.
     *
     * @param FieldValue $value
     * @return FieldValue
     */
    public function resolveField(FieldValue $value)
    {
        return $value->setResolver(
            function (?Model $model) {
                // Fetch the count by relation
                $relation = $this->directiveArgValue('relation');
                if (! is_null($relation)) {
                    return $model->{$relation}()->count();
                }

                // Else we try to fetch by model.
                $modelArg = $this->directiveArgValue('model');
                if (! is_null($modelArg)) {
                    return $this->namespaceModelClass($modelArg)::count();
                }

                throw new DirectiveException(
                    "A `model` or `relation argument must be assigned to the '{$this->name()}' directive on '{$this->nodeName()}"
                );
            }
        );
    }
}
