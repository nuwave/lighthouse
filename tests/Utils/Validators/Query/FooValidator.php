<?php

namespace Tests\Utils\Validators\Query;

use Nuwave\Lighthouse\Validation\Validator;

final class FooValidator extends Validator
{
    public function rules(): array
    {
        return [
            'email' => ['email'],
        ];
    }
}
