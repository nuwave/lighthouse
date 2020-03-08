<?php

namespace Tests\Utils\Validators;

use Nuwave\Lighthouse\Validation\InputValidator;

class SelfValidatingValidator extends InputValidator
{
    public function rules(): array
    {
        // This also tests the input is being passed
        return $this->input->toArray();
    }
}
