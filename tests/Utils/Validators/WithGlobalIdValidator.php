<?php

namespace Tests\Utils\Validators;

use Nuwave\Lighthouse\Validation\Validator;

class WithGlobalIdValidator extends Validator
{
    public function rules(): array
    {
        return [
            'id' => ['array'],
        ];
    }
}
