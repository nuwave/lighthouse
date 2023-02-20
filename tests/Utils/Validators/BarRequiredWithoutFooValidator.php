<?php

namespace Tests\Utils\Validators;

use Nuwave\Lighthouse\Validation\Validator;

final class BarRequiredWithoutFooValidator extends Validator
{
    public function rules(): array
    {
        return [
            'bar' => ['required_without:foo'],
        ];
    }
}
