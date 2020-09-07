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
  Specify the messages to return if the validators fail.
  Specified as an input object that maps rules to messages,
  e.g. { email: "Must be a valid email", max: "The input was too long" }
  """
  messages: RulesMessageMap
) repeatable on ARGUMENT_DEFINITION | INPUT_FIELD_DEFINITION
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
