<?php declare(strict_types=1);

namespace Nuwave\Lighthouse\Exceptions;

use GraphQL\Error\ClientAware;
use GraphQL\Error\ProvidesExtensions;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Validation\ValidationException as LaravelValidationException;

class ValidationException extends \Exception implements ClientAware, ProvidesExtensions
{
    public const KEY = 'validation';

    public function __construct(
        string $message,
        protected Validator $validator,
    ) {
        parent::__construct($message);
    }

    public static function fromLaravel(LaravelValidationException $laravelException): self
    {
        return new static($laravelException->getMessage(), $laravelException->validator);
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
            LaravelValidationException::withMessages($messages),
        );
    }

    public function isClientSafe(): bool
    {
        return true;
    }

    /** @return array{validation: array<string, array<int, string>>} */
    public function getExtensions(): array
    {
        return [
            self::KEY => $this->validator->errors()->messages(),
        ];
    }
}
