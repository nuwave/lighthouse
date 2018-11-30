<?php

namespace Nuwave\Lighthouse\Schema\Directives\Args;

use Nuwave\Lighthouse\Support\NoValue;
use Illuminate\Contracts\Validation\Validator;
use Nuwave\Lighthouse\Support\Traits\HasErrorBuffer;
use Nuwave\Lighthouse\Support\Traits\HasArgumentPath;

trait HandleRulesDirective
{
    use HasErrorBuffer, HasArgumentPath;

    /**
     * @param mixed    $argumentValue
     * @param \Closure $next
     *
     * @return mixed
     */
    public function handleArgument($argumentValue, \Closure $next)
    {
        $value = $next($argumentValue);

        $rules = (array) $this->getRules();

        if (! \count($rules)) {
            return $value;
        }

        if ($argumentValue instanceof NoValue && ! \in_array('required', $rules, true)) {
            return $value;
        }

        $validator = $this->createValidator($value instanceof NoValue ? null : $value, $rules);

        if (! $validator->fails()) {
            return $value;
        }

        $this->accumulateError($validator);

        return $value;
    }

    /**
     * @param $value
     * @param array $rules
     *
     * @return \Illuminate\Contracts\Validation\Factory|Validator
     */
    protected function createValidator($value, array $rules)
    {
        $argumentName = $this->getArgumentName();

        $validator = validator(
            [$argumentName => $value],
            [$argumentName => $rules],
            (array) $this->getMessages()
        );

        $validator->setAttributeNames([$this->getArgumentName() => $this->argumentPathAsDotNotation()]);

        return $validator;
    }

    /**
     * Accumulate the error to ErrorBuffer.
     *
     * @param Validator $validator
     */
    protected function accumulateError(Validator $validator)
    {
        $errorMessages = $validator->errors()->get($this->getArgumentName());
        foreach ($errorMessages as $errorMessage) {
            $this->errorBuffer()->push($errorMessage, $this->argumentPathAsDotNotation());
        }
    }

    /**
     * @return array|null
     */
    protected function getRules()
    {
        return $this->directiveArgValue('apply');
    }

    /**
     * @return array|null
     */
    protected function getMessages()
    {
        return $this->directiveArgValue('messages');
    }

    /**
     * @return string
     */
    protected function getArgumentName(): string
    {
        return $this->definitionNode->name->value;
    }
}
