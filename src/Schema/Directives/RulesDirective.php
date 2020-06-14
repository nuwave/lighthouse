<?php

namespace Nuwave\Lighthouse\Schema\Directives;

use GraphQL\Language\AST\FieldDefinitionNode;
use GraphQL\Language\AST\InputValueDefinitionNode;
use GraphQL\Language\AST\ObjectTypeDefinitionNode;
use Illuminate\Support\Collection;
use Nuwave\Lighthouse\Exceptions\DefinitionException;
use Nuwave\Lighthouse\Schema\AST\DocumentAST;
use Nuwave\Lighthouse\Support\Contracts\ArgDirective;
use Nuwave\Lighthouse\Support\Contracts\ArgManipulator;
use Nuwave\Lighthouse\Support\Contracts\DefinedDirective;
use Nuwave\Lighthouse\Support\Contracts\HasArgumentPath as HasArgumentPathContract;
use Nuwave\Lighthouse\Support\Contracts\ProvidesRules;
use Nuwave\Lighthouse\Support\Traits\HasArgumentPath as HasArgumentPathTrait;

class RulesDirective extends BaseDirective implements ArgDirective, ProvidesRules, HasArgumentPathContract, DefinedDirective, ArgManipulator
{
    use HasArgumentPathTrait;

    public static function definition(): string
    {
        return /** @lang GraphQL */ <<<'SDL'
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

    public function manipulateArgDefinition(
        DocumentAST &$documentAST,
        InputValueDefinitionNode &$argDefinition,
        FieldDefinitionNode &$parentField,
        ObjectTypeDefinitionNode &$parentType
    ) {
        $rules = $this->directiveArgValue('apply');

        if (! is_array($rules)) {
            throw new DefinitionException("The apply argument of @rules on has to be an array, got: {$rules}");
        }
    }
}
