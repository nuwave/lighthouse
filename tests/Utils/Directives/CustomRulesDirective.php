<?php

namespace Tests\Utils\Directives;

use Nuwave\Lighthouse\Support\Contracts\ArgDirective;
use Nuwave\Lighthouse\Validation\BaseRulesDirective;

/**
 * TODO remove once we can use @rules repeatedly.
 */
class CustomRulesDirective extends BaseRulesDirective implements ArgDirective
{
    public static function definition(): string
    {
        return /** @lang GraphQL */ <<<'SDL'
"""
Validate an argument using [Laravel validation](https://laravel.com/docs/validation).
"""
directive @customRules(
  """
  Specify the validation rules to apply to the field.
  This can either be a reference to [Laravel's built-in validation rules](https://laravel.com/docs/validation#available-validation-rules),
  or the fully qualified class name of a custom validation rule.

  Rules that mutate the incoming arguments, such as `exclude_if`, are not supported
  by Lighthouse. Use ArgTransformerDirectives or FieldMiddlewareDirectives instead.
  """
  apply: [String!]!

  """
  Specify the messages to return if the validators fail.
  Specified as an input object that maps rules to messages,
  e.g. { email: "Must be a valid email", max: "The input was too long" }
  """
  messages: RulesMessageMap
) on ARGUMENT_DEFINITION | INPUT_FIELD_DEFINITION
SDL;
    }
}
