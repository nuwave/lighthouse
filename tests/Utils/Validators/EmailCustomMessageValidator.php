<?php

namespace Tests\Utils\Validators;

use Nuwave\Lighthouse\Validation\InputValidator;

class EmailCustomMessageValidator extends InputValidator
{
    const MESSAGE = 'this is a custom error message';

    public function rules(): array
    {
        return [
            'email' => ['email'],
        ];
    }

    public function messages(): array
    {
        return ['email.email' => self::MESSAGE];
    }
}
