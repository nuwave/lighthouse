<?php

namespace Nuwave\Lighthouse\Schema\Directives\Args;

use Illuminate\Contracts\Validation\Validator;
use Nuwave\Lighthouse\Schema\Values\ArgumentValue;
use Nuwave\Lighthouse\Support\Traits\HasErrorBuffer;
use Nuwave\Lighthouse\Support\Traits\HasArgumentPath;
use Nuwave\Lighthouse\Schema\Directives\BaseDirective;
use Nuwave\Lighthouse\Support\Contracts\ArgMiddleware;
use Nuwave\Lighthouse\Support\Contracts\HasErrorBuffer as HasErrorBufferContract;
use Nuwave\Lighthouse\Support\Contracts\HasArgumentPath as HasArgumentPathContract;

class RulesDirective extends BaseDirective implements ArgMiddleware, HasErrorBufferContract, HasArgumentPathContract
{
    use HasErrorBuffer, HasArgumentPath;

    const ERROR_TYPE = 'validation';

    /**
     * Name of the directive.
     *
     * @return string
     */
    public function name(): string
    {
        return 'rules';
    }

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

        $validator = $this->createValidator($value, $rules);

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
        $this->errorBuffer()->setErrorType(static::ERROR_TYPE);
        $errorMessage = $validator->errors()->get($this->getArgumentName());

        $this->errorBuffer()->push($errorMessage[0], $this->argumentPathAsDotNotation());
    }

    /**
     * @throws \Exception
     */
    public function flushErrorBuffer()
    {
        $path = $this->resolveInfo()->path;
        $this->errorBuffer->flush("Validation failed for the field [$path]");
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

    /**
     * Get the flush error message.
     * This method will be called right before ErrorBuffer instance be flushed.
     *
     * @param ArgumentValue $rootArgumentValue
     * @param string        $path
     *
     * @return string
     */
    public static function getFlushErrorMessage(ArgumentValue $rootArgumentValue, string $path): string
    {
        return "Validation failed for the field [$path].";
    }
}
