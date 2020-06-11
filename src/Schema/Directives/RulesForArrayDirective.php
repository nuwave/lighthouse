<?php

namespace Nuwave\Lighthouse\Schema\Directives;

use Illuminate\Support\Arr;
use Illuminate\Support\Collection;
use Nuwave\Lighthouse\Support\Contracts\ArgDirectiveForArray;
use Nuwave\Lighthouse\Support\Contracts\DefinedDirective;
use Nuwave\Lighthouse\Support\Contracts\HasArgumentPath as HasArgumentPathContract;
use Nuwave\Lighthouse\Support\Contracts\ProvidesRules;
use Nuwave\Lighthouse\Support\Traits\HasArgumentPath as HasArgumentPathTrait;

class RulesForArrayDirective extends BaseDirective implements ArgDirectiveForArray, ProvidesRules, HasArgumentPathContract, DefinedDirective
{
    use HasArgumentPathTrait;

    public static function definition(): string
    {
        return /** @lang GraphQL */ <<<'SDL'
"""
Run validation on an array itself, using [Laravel built-in validation](https://laravel.com/docs/validation).
"""
directive @rulesForArray(
  """
  Specify the validation rules to apply to the field.
  This can either be a reference to any of Laravel\'s built-in validation rules: https://laravel.com/docs/validation#available-validation-rules,
  or the fully qualified class name of a custom validation rule.
  """
  apply: [String!]!

  """
  Specify the messages to return if the validators fail.
  Specified as an input object that maps rules to messages,
  e.g. { email: "Must be a valid email", max: "The input was too long" }
  """
  messages: [RulesMessageMap!]
) on ARGUMENT_DEFINITION | INPUT_FIELD_DEFINITION
SDL;
    }

    public function rules(): array
    {
        $rules = $this->directiveArgValue('apply');

        if (! in_array('array', $rules)) {
            $rules = Arr::prepend($rules, 'array');
        }

        // Custom rules may be referenced through their fully qualified class name.
        // The Laravel validator expects a class instance to be passed, so we
        // resolve any given rule where a corresponding class exists.
        foreach ($rules as $key => $rule) {
            if (class_exists($rule)) {
                $rules[$key] = app($rule);
            }
        }

        return [$this->argumentPathAsDotNotation() => $rules];
    }

    public function messages(): array
    {
        return (new Collection($this->directiveArgValue('messages')))
            ->mapWithKeys(
                /**
                 * @return array<string, string>
                 */
                function (string $message, string $rule): array {
                    $argumentPath = $this->argumentPathAsDotNotation();

                    return ["{$argumentPath}.{$rule}" => $message];
                }
            )
            ->all();
    }
}
