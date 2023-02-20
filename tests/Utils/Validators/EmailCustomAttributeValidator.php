<?php

namespace Tests\Utils\Validators;

use Nuwave\Lighthouse\Validation\Validator;

final class EmailCustomAttributeValidator extends Validator
{
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
