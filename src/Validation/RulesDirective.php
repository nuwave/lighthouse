<?php

namespace Nuwave\Lighthouse\Validation;

use Nuwave\Lighthouse\Support\Contracts\ArgDirective;

class RulesDirective extends BaseRulesDirective implements ArgDirective
{
    public static function definition(): string
    {
        return /** @lang GraphQL */ <<<'GRAPHQL'
"""
Validate an argument using [Laravel validation](https://laravel.com/docs/validation).
"""
directive @rules(
  """
  Specify the validation rules to apply to the field.
  This can either be a reference to [Laravel's built-in validation rules](https://laravel.com/docs/validation#available-validation-rules),
  or the fully qualified class name of a custom validation rule.

  Rules that mutate the incoming arguments, such as `exclude_if`, are not supported
  by Lighthouse. Use ArgTransformerDirectives or FieldMiddlewareDirectives instead.
  """
  apply: [String!]!

  """
  Specify a custom attribute name to use in your validation message.
  """
  attribute: String

  """
  Specify the messages to return if the validators fail.
  Specified as an input object that maps rules to messages,
  e.g. { email: "Must be a valid email", max: "The input was too long" }
  """
  messages: RulesMessageMap
) repeatable on ARGUMENT_DEFINITION | INPUT_FIELD_DEFINITION
GRAPHQL;
    }
}
