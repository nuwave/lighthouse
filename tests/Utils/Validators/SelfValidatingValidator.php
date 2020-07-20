<?php

namespace Tests\Utils\Validators;

use Nuwave\Lighthouse\Validation\Validator;

class SelfValidatingValidator extends Validator
{
    public function rules(): array
    {
        // This also tests the input is being passed
        return $this->args->toArray();
    }
}
