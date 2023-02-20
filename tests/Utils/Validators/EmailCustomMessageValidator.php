<?php

namespace Tests\Utils\Validators;

use Nuwave\Lighthouse\Validation\Validator;

final class EmailCustomMessageValidator extends Validator
{
    public const MESSAGE = 'this is a custom error message';

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
