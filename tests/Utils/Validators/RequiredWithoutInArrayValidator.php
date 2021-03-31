<?php

namespace Tests\Utils\Validators;

use Nuwave\Lighthouse\Validation\Validator;

class RequiredWithoutInArrayValidator extends Validator
{
    public function rules(): array
    {
        return [
            'items.*.thing.an_id' => [
                'required_without:items.*.thing.some_data',
            ],
            'items.*.thing.some_data' => [
                'required_without:items.*.thing.an_id',
            ],
        ];
    }
}
