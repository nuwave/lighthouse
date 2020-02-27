<?php

namespace Nuwave\Lighthouse\Validation;

use Nuwave\Lighthouse\Exceptions\DefinitionException;
use Nuwave\Lighthouse\Schema\Directives\BaseDirective;
use Nuwave\Lighthouse\Support\Contracts\ArgDirective;
use Nuwave\Lighthouse\Support\Contracts\DefinedDirective;
use Nuwave\Lighthouse\Support\Contracts\ProvidesRules;
use Nuwave\Lighthouse\Support\Traits\HasArgumentValue;

class ValidatorDirective extends BaseDirective implements ArgDirective, ProvidesRules, DefinedDirective
{
    use HasArgumentValue;

    /**
     * @var InputValidator
     */
    protected $validator;

    public static function definition(): string
    {
        return /** @lang GraphQL */ <<<'SDL'
"""
Provide validation rules through a PHP class.
"""
directive @validator(
  """
  The name of the class to use.
  """
  class: String
) on ARGUMENT_DEFINITION | INPUT_FIELD_DEFINITION | FIELD_DEFINITION | INPUT_OBJECT
SDL;
    }

    /**
     * @return mixed[]
     * @throws DefinitionException
     */
    public function rules(): array
    {
        return $this->validator()->rules();
    }

    /**
     * @return string[]
     */
    public function messages(): array
    {
        return $this->validator()->messages();
    }

    protected function validator(): InputValidator
    {
        if (! $this->validator) {
            $classCandidate = $this->directiveArgValue('validator')
                ?? $this->nodeName().'Validator';
            $validatorClass = $this->inputValidatorClass($classCandidate);

            $this->validator = app($validatorClass);
            $this->validator->setInput($this->argumentValue);
        }

        return $this->validator;
    }
}
