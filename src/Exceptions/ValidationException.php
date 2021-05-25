<?php

namespace Nuwave\Lighthouse\Exceptions;

use Exception;
use Illuminate\Container\Container;
use Illuminate\Contracts\Validation\Factory as ValidatorFactory;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Support\Arr;

class ValidationException extends Exception implements RendersErrorsExtensions
{
    const CATEGORY = 'validation';

    /**
     * @var \Illuminate\Contracts\Validation\Validator
     */
    protected $validator;

    public function __construct(string $message, Validator $validator)
    {
        parent::__construct($message);

        $this->validator = $validator;
    }

    /**
     * Instantiate from a plain array of messages.
     *
     * @see \Illuminate\Validation\ValidationException::withMessages()
     *
     * @param  array<string, string|array<string>>  $messages
     */
    public static function withMessages(array $messages): ValidationException
    {
        /** @var \Illuminate\Contracts\Validation\Factory $validatorFactory */
        $validatorFactory = Container::getInstance()->make(ValidatorFactory::class);
        $validator = $validatorFactory->make([], []);
        foreach ($messages as $key => $value) {
            foreach (Arr::wrap($value) as $message) {
                $validator->errors()->add($key, $message);
            }
        }

        return new static('Validation failed.', $validator);
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
