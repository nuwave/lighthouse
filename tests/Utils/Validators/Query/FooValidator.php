<?php

namespace Tests\Utils\Validators\Query;

use Nuwave\Lighthouse\Validation\InputValidator;

class FooValidator extends InputValidator
{
    public function rules(): array
    {
        return [
            'email' => ['email'],
        ];
    }
}
