<?php

namespace Nuwave\Lighthouse\Schema\Directives;

use GraphQL\Language\AST\FieldDefinitionNode;
use GraphQL\Language\AST\InputObjectTypeDefinitionNode;
use GraphQL\Language\AST\InputValueDefinitionNode;
use GraphQL\Language\AST\ObjectTypeDefinitionNode;
use Illuminate\Support\Collection;
use Nuwave\Lighthouse\Exceptions\DefinitionException;
use Nuwave\Lighthouse\Execution\InputValidator;
use Nuwave\Lighthouse\Schema\AST\DocumentAST;
use Nuwave\Lighthouse\Support\Contracts\ArgDirective;
use Nuwave\Lighthouse\Support\Contracts\ArgManipulator;
use Nuwave\Lighthouse\Support\Contracts\DefinedDirective;
use Nuwave\Lighthouse\Support\Contracts\HasArgPathValue;
use Nuwave\Lighthouse\Support\Contracts\HasArgumentPath as HasArgumentPathContract;
use Nuwave\Lighthouse\Support\Contracts\ProvidesRules;
use Nuwave\Lighthouse\Support\Traits\HasArgumentPath as HasArgumentPathTrait;

class RulesDirective extends BaseDirective implements ArgDirective, ProvidesRules, HasArgumentPathContract, DefinedDirective, ArgManipulator, HasArgPathValue
{
    use HasArgumentPathTrait;

    /**
     * @var array
     */
    private $argPathValue;

    /**
     * @var InputValidator
     */
    private $validator;

    public static function definition(): string
    {
        return /** @lang GraphQL */ <<<'SDL'
"""
Validate an argument or input type using [Laravel validation](https://laravel.com/docs/validation).
"""
directive @rules(
  """
  Specify the validation rules to apply to the field.
  This can either be a reference to [Laravel's built-in validation rules](https://laravel.com/docs/validation#available-validation-rules),
  or the fully qualified class name of a custom validation rule.

  Rules that mutate the incoming arguments, such as `exclude_if`, are not supported
  by Lighthouse. Use ArgTransformerDirectives or FieldMiddlewareDirectives instead.
  """
  apply: [String!]

  """
  Specify the messages to return if the validators fail.
  Specified as an input object that maps rules to messages,
  e.g. { email: "Must be a valid email", max: "The input was too long" }
  """
  messages: [RulesMessageMap!]

  """
  Specify the validator that should be used to validate a given input type.
  """
  validator: String
) on ARGUMENT_DEFINITION | INPUT_FIELD_DEFINITION | INPUT_OBJECT
SDL;
    }

    /**
     * @return mixed[]
     * @throws DefinitionException
     */
    public function rules(): array
    {
        if ($this->definitionNode instanceof InputObjectTypeDefinitionNode) {
            return $this->rulesForInputObject();
        }

        return $this->rulesForField();
    }

    /**
     * @return string[]
     */
    public function messages(): array
    {
        if ($this->definitionNode instanceof InputObjectTypeDefinitionNode) {
            return $this->messagesForInputObject();
        }

        return (new Collection($this->directiveArgValue('messages')))
            ->mapWithKeys(function (string $message, string $rule): array {
                $argumentPath = $this->argumentPathAsDotNotation();

                return ["{$argumentPath}.{$rule}" => $message];
            })
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

    public function setArgPathValue($value = null): void
    {
        $this->argPathValue = $value;
    }

    /**
     * @param array $array
     *
     * @return array
     */
    private function addFullInputPathToKeys(array $array): array
    {
        $argumentPath = $this->argumentPathAsDotNotation();

        return collect($array)
            ->mapWithKeys(function ($value, $key) use ($argumentPath) {
                return [$argumentPath.'.'.$key => $value];
            })
            ->toArray();
    }

    /**
     * @return array
     * @throws DefinitionException
     */
    private function rulesForInputObject(): array
    {
        $class = $this->directiveArgValue('validator') ?? $this->inputValidatorClass($this->nodeName().'Validator');

        $validator = new $class($this->argPathValue);
        $this->validator = $validator;
        $rules = $this->validator->rules();

        return $this->addFullInputPathToKeys($rules);
    }

    /**
     * @return array
     */
    private function rulesForField(): array
    {
        $rules = $this->directiveArgValue('apply');

        // Custom rules may be referenced through their fully qualified class name.
        // The Laravel validator expects a class instance to be passed, so we
        // resolve any given rule where a corresponding class exists.
        foreach ($rules as $key => $rule) {
            if (class_exists($rule)) {
                $rules[$key] = resolve($rule);
            }
        }

        return [$this->argumentPathAsDotNotation() => $rules];
    }

    private function messagesForInputObject(): array
    {
        return $this->addFullInputPathToKeys($this->validator->messages());
    }
}
