<?php

namespace Tests\Utils\Validators;

use Nuwave\Lighthouse\Validation\Validator;

final class EmailCustomAttributeValidator extends Validator
{
    public const MESSAGE = 'The email address must be a valid email address.';

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
