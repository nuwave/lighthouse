<?php

namespace Nuwave\Lighthouse\Exceptions;

use Illuminate\Support\Arr;
use Exception;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Support\Facades\Validator as ValidatorFacade;

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

    public function isClientSafe(): bool
    {
        return true;
    }

    public function getCategory(): string
    {
        return self::CATEGORY;
    }
    
    public static function withMessage(array $messages)
    {
        $validator = tap(ValidatorFacade::make([], []), function($validator) use ($messages) {
            foreach ($messages as $key => $value) {
                foreach (Arr::wrap($value) as $message) {
                    $validator->errors()->add($key, $message);
                }
            }
        });

        return new static('Validation Failed', $validator);
    }

    public function extensionsContent(): array
    {
        return [
            'validation' => $this->validator->errors()->messages(),
        ];
    }
}
