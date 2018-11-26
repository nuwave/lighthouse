<?php

namespace Nuwave\Lighthouse\Execution;

use Nuwave\Lighthouse\Exceptions\GenericException;

class ErrorBuffer
{
    /**
     * @var array
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
     * @param string        $errorType
     * @param \Closure|null $exceptionResolver
     */
    public function __construct(string $errorType = 'generic', \Closure $exceptionResolver = null)
    {
        $this->errorType = $errorType;
        $this->exceptionResolver = $exceptionResolver ?? $this->getExceptionResolver();
    }

    /**
     * Get the Exception resolver.
     *
     * @return \Closure
     */
    protected function getExceptionResolver(): \Closure
    {
        return function ($errorMessage) {
            return (new GenericException($errorMessage))
                ->setExtensions([$this->errorType => $this->errors])
                ->setCategory($this->errorType);
        };
    }

    /**
     * Set the Exception resolver.
     *
     * @param \Closure $exceptionResolver
     *
     * @return static
     */
    public function setExceptionResolver(\Closure $exceptionResolver): self
    {
        $this->exceptionResolver = $exceptionResolver;

        return $this;
    }

    /**
     * Resolve the exception class.
     *
     * @param mixed ...$args
     *
     * @return mixed
     */
    protected function resolveException(...$args)
    {
        return ($this->exceptionResolver)(...$args);
    }

    /**
     * Push an error message into the buffer.
     *
     * @param $key
     * @param $errorMessage
     *
     * @return static
     */
    public function push(string $errorMessage, string $key = null): self
    {
        if (null === $key) {
            $this->errors[] = $errorMessage;

            return $this;
        }

        $errorRecord = data_get($this->errors, $key);

        if ($errorRecord) {
            $errorRecord[] = $errorMessage;
        } else {
            $errorRecord = [$errorMessage];
        }

        data_set($this->errors, $key, $errorRecord);

        return $this;
    }

    /**
     * Flush the errors.
     *
     * @param string $errorMessage
     *
     * @throws \Exception
     */
    public function flush(string $errorMessage)
    {
        if (! $this->hasErrors()) {
            return;
        }

        $exception = $this->resolveException($errorMessage, $this);

        $this->clearErrors();

        throw $exception;
    }

    /**
     * Rest the errors to empty array.
     */
    public function clearErrors()
    {
        $this->errors = [];
    }

    /**
     * Get the error type.
     *
     * @return string
     */
    public function errorType(): string
    {
        return $this->errorType;
    }

    /**
     * Set the error type.
     *
     * @param string $errorType
     *
     * @return static
     */
    public function setErrorType(string $errorType): self
    {
        $this->errorType = $errorType;

        return $this;
    }

    /**
     * @return bool
     */
    public function hasErrors(): bool
    {
        return (bool) \count($this->errors);
    }
}
