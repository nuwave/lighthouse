<?php


namespace Nuwave\Lighthouse\Schema\Directives;

use Nuwave\Lighthouse\Execution\InputTypeValidator;
use Nuwave\Lighthouse\Support\Contracts\ArgDirective;
use Nuwave\Lighthouse\Support\Contracts\HasArgumentPath;
use Nuwave\Lighthouse\Support\Contracts\HasInput;
use Nuwave\Lighthouse\Support\Contracts\ProvidesRules;

/**
 * Class ValidateDirective
 */
class ValidateDirective extends BaseDirective implements ArgDirective, ProvidesRules, HasInput, HasArgumentPath
{

    /**
     * @var array
     */
    private $input;

    /**
     * @var InputTypeValidator
     */
    private $validator;
    /**
     * @var array
     */
    private $argumentPath;

    /**
     * @inheritDoc
     */
    public static function definition()
    {
        return /** @lang GraphQL */ <<<'SDL'
"""
Validate given input object with a validation class.
"""
directive @validate(validator: String) on INPUT_OBJECT
SDL;
    }

    /**
     * @inheritDoc
     */
    public function name(): string
    {
        return 'validate';
    }

    /**
     * @inheritDoc
     * @throws \Nuwave\Lighthouse\Exceptions\DefinitionException
     */
    public function rules(): array
    {
        $class = $this->directiveArgValue('validator') ?? $this->inputTypeValidatorClass($this->nodeName() . 'Validator');

        $validator = new $class($this->input);
        $this->validator = $validator;

        return $this->addFullInputPathToKeys($this->validator->rules());
    }

    /**
     * @inheritDoc
     */
    public function messages(): array
    {
        return $this->addFullInputPathToKeys($this->validator->messages());
    }

    public function setInput(array $args): void
    {
        $this->input = $args;
    }

    /**
     * @inheritDoc
     */
    public function argumentPath(): array
    {
        return $this->argumentPath;
    }

    /**
     * @inheritDoc
     */
    public function setArgumentPath(array $argumentPath)
    {
        $this->argumentPath = $argumentPath;
    }

    /**
     * @param array $collection
     *
     * @return array
     */
    private function addFullInputPathToKeys(array $collection): array
    {
        $argumentBasePathDotNotation = implode('.', $this->argumentPath);

        return collect($this->validator->rules())
            ->mapWithKeys(function ($value, $key) use ($argumentBasePathDotNotation) {
                return [$argumentBasePathDotNotation . '.' . $key => $value];
            })
            ->toArray();
    }
}
