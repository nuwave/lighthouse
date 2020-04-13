<?php

namespace Nuwave\Lighthouse\Execution;

use Closure;
use Nuwave\Lighthouse\Exceptions\GenericException;

class ErrorBuffer
{
    /**
     * The gathered error messages.
     *
     * @var string[]|string[][]
     */
    protected $errors = [];

    /**
     * @var string
     */
    protected $errorType;

    /**
     * @var \Closure
     */
    protected $exceptionResolver;

    /**
     * ErrorBuffer constructor.
     *
     * @return void
     */
    public function __construct(string $errorType = 'generic', ?Closure $exceptionResolver = null)
    {
        $this->errorType = $errorType;
        $this->exceptionResolver = $exceptionResolver ?? $this->defaultExceptionResolver();
    }

    /**
     * Construct a default exception resolver.
     */
    protected function defaultExceptionResolver(): Closure
    {
        return function (string $errorMessage): GenericException {
            return (new GenericException($errorMessage))
                ->setExtensions([$this->errorType => $this->errors])
                ->setCategory($this->errorType);
        };
    }

    /**
     * Set the Exception resolver.
     *
     * @return $this
     */
    public function setExceptionResolver(Closure $exceptionResolver): self
    {
        $this->exceptionResolver = $exceptionResolver;

        return $this;
    }

    /**
     * Resolve the exception by calling the exception handler with the given args.
     *
     * @param  mixed  ...$args
     */
    protected function resolveException(...$args)
    {
        return ($this->exceptionResolver)(...$args);
    }

    /**
     * Push an error message into the buffer.
     *
     * @return $this
     */
    public function push(string $errorMessage, ?string $key = null): self
    {
        if ($key === null) {
            $this->errors[] = $errorMessage;
        } else {
            $this->errors[$key][] = $errorMessage;
        }

        return $this;
    }

    /**
     * Flush the errors.
     *
     *
     * @throws \Exception
     */
    public function flush(string $errorMessage): void
    {
        if (! $this->hasErrors()) {
            return;
        }

        $exception = $this->resolveException($errorMessage, $this);

        $this->clearErrors();

        throw $exception;
    }

    /**
     * Reset the errors to an empty array.
     */
    public function clearErrors(): void
    {
        $this->errors = [];
    }

    /**
     * Get the error type.
     */
    public function errorType(): string
    {
        return $this->errorType;
    }

    /**
     * Set the error type.
     *
     * @return $this
     */
    public function setErrorType(string $errorType): self
    {
        $this->errorType = $errorType;

        return $this;
    }

    /**
     * Have we encountered any errors yet?
     */
    public function hasErrors(): bool
    {
        return count($this->errors) > 0;
    }
}
