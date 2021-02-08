<?php

namespace Nuwave\Lighthouse\Validation;

use Illuminate\Support\Arr;
use Nuwave\Lighthouse\Support\Contracts\ArgDirectiveForArray;

class RulesForArrayDirective extends BaseRulesDirective implements ArgDirectiveForArray
{
    public static function definition(): string
    {
        return /** @lang GraphQL */ <<<'GRAPHQL'
"""
Run validation on an array itself, using [Laravel built-in validation](https://laravel.com/docs/validation).
"""
directive @rulesForArray(
  """
  Specify the validation rules to apply to the field.
  This can either be a reference to any of Laravel's built-in validation rules: https://laravel.com/docs/validation#available-validation-rules,
  or the fully qualified class name of a custom validation rule.
  """
  apply: [String!]!

  """
  Specify a custom attribute name to use in your validation message.
  """
  attribute: String

  """
  Specify the messages to return if the validators fail.
  """
  messages: [RulesForArrayMessage!]
) repeatable on ARGUMENT_DEFINITION | INPUT_FIELD_DEFINITION

"""
Input for the `messages` argument of `@rulesForArray`.
"""
input RulesForArrayMessage {
    """
    Name of the rule, e.g. `"email"`.
    """
    rule: String!

    """
    Message to display if the rule fails, e.g. `"Must be a valid email"`.
    """
    message: String!
}
GRAPHQL;
    }

    public function rules(): array
    {
        $rules = parent::rules();

        if (! in_array('array', $rules)) {
            $rules = Arr::prepend($rules, 'array');
        }

        return $rules;
    }
}
