<?php

namespace Nuwave\Lighthouse\Exceptions;

use Exception;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Validation\ValidationException as LaravelValidationException;

class ValidationException extends Exception implements RendersErrorsExtensions
{
    public const CATEGORY = 'validation';

    /**
     * @var \Illuminate\Contracts\Validation\Validator
     */
    protected $validator;

    public function __construct(string $message, Validator $validator)
    {
        parent::__construct($message);

        $this->validator = $validator;
    }

    public static function fromLaravel(LaravelValidationException $laravelException): self
    {
        return new static(
            $laravelException->getMessage(),
            $laravelException->validator
        );
    }

    /**
     * Instantiate from a plain array of messages.
     *
     * @see \Illuminate\Validation\ValidationException::withMessages()
     *
     * @param  array<string, string|array<string>>  $messages
     */
    public static function withMessages(array $messages): self
    {
        return static::fromLaravel(
            LaravelValidationException::withMessages($messages)
        );
    }

    public function isClientSafe(): bool
    {
        return true;
    }

    public function getCategory(): string
    {
        return self::CATEGORY;
    }

    public function extensionsContent(): array
    {
        return [
            self::CATEGORY => $this->validator->errors()->messages(),
        ];
    }
}
