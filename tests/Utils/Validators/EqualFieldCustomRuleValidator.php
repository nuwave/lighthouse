<?php

namespace Tests\Utils\Validators;

use Nuwave\Lighthouse\Validation\Validator;
use Tests\Utils\Rules\EqualFieldRule;

class EqualFieldCustomRuleValidator extends Validator
{
    public function rules(): array
    {
        return [
            'bar' => [new EqualFieldRule()],
        ];
    }
}
