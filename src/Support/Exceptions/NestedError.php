<?php


namespace Nuwave\Lighthouse\Support\Exceptions;


use Illuminate\Validation\ValidationException;

class NestedError extends Error
{
    private $errors;

    /**
     * NestedError constructor.
     *
     * @param array|Error[] $errors
     */
    public function __construct(array $errors)
    {
        parent::__construct("NestedError");
        $this->errors = $errors;
    }

    public function toArray() : array
    {
        return collect($this->errors)->map(function (Error $error) {
            return $error->toArray();
        })->values()->all();
    }

    public static function fromValidationException(ValidationException $exception) : NestedError
    {
        return new NestedError(
            collect($exception->errors())->map(function (array $messages) {
                return collect($messages)->map(function (string $message) {
                    return new Error($message);
                })->values()->all();
            })->values()->flatten()->all()
        );
    }

}
