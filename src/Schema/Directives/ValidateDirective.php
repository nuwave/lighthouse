<?php

namespace Nuwave\Lighthouse\Schema\Directives;

use Closure;
use Nuwave\Lighthouse\Execution\InputValidator;
use Nuwave\Lighthouse\Schema\Values\FieldValue;
use Nuwave\Lighthouse\Support\Contracts\ArgDirective;
use Nuwave\Lighthouse\Support\Contracts\FieldMiddleware;
use Nuwave\Lighthouse\Support\Contracts\HasArgumentPath;
use Nuwave\Lighthouse\Support\Contracts\HasArgPathValue;
use Nuwave\Lighthouse\Support\Contracts\ProvidesRules;

class ValidateDirective extends BaseDirective implements ArgDirective, ProvidesRules, HasArgPathValue, HasArgumentPath, FieldMiddleware
{
    /**
     * @var array
     */
    private $input;

    /**
     * @var InputValidator
     */
    private $validator;

    /**
     * @var array
     */
    private $argumentPath;

    public static function definition()
    {
        return /** @lang GraphQL */ <<<'SDL'
"""
Validate given input object with a validation class.
"""
directive @validate(
    """
    Secify the class name of the validator to use.
    This is only when the valdidator is located somewhere else than the default location.
    """
    validator: String

) on INPUT_OBJECT
SDL;
    }

    public function name(): string
    {
        return 'validate';
    }

    /**
     * @throws \Nuwave\Lighthouse\Exceptions\DefinitionException
     */
    public function rules(): array
    {
        $class = $this->directiveArgValue('validator') ?? $this->inputValidatorClass($this->nodeName().'Validator');

        $validator = new $class($this->input);
        $this->validator = $validator;

        return $this->addFullInputPathToKeys();
    }

    public function messages(): array
    {
        return $this->addFullInputPathToKeys($this->validator->messages());
    }

    public function setArgPathValue(array $args): void
    {
        $this->input = $args;
    }

    public function argumentPath(): array
    {
        return $this->argumentPath;
    }

    public function setArgumentPath(array $argumentPath)
    {
        $this->argumentPath = $argumentPath;
    }

    /**
     * @param array $array
     *
     * @return array
     */
    private function addFullInputPathToKeys(array $array): array
    {
        $argumentBasePathDotNotation = implode('.', $this->argumentPath);

        return collect($array)
            ->mapWithKeys(function ($value, $key) use ($argumentBasePathDotNotation) {
                return [$argumentBasePathDotNotation.'.'.$key => $value];
            })
            ->toArray();
    }

    public function handleField(FieldValue $fieldValue, Closure $next): FieldValue
    {
       $foo = 'bar';
       return $fieldValue;
    }
}
