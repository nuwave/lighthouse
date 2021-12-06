<?php

namespace Tests\Utils\Validators;

use Nuwave\Lighthouse\Validation\Validator;

class EmailCustomAttributeValidator extends Validator
{
    const MESSAGE = 'The email address must be a valid email address.';

    public function rules(): array
    {
        return [
            'email' => ['email'],
        ];
    }

    public function attributes(): array
    {
        return ['email' => 'email address'];
    }
}
