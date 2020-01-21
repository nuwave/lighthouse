<?php

namespace Tests\Utils\Directives;

use Illuminate\Validation\Rule;
use Nuwave\Lighthouse\Schema\Directives\ValidationDirective;

class ComplexValidationDirective extends ValidationDirective
{
    const UNIQUE_VALIDATION_MESSAGE = 'Used to test this exact validation is triggered';

    /**
     * @return mixed[]
     */
    public function rules(): array
    {
        return [
            'id' => ['required'],
            'name' => [
                'sometimes',
                Rule::unique('users', 'name')
                    ->ignore($this->args['id'], 'id'),
            ],
        ];
    }

    /**
     * @return string[]
     */
    public function messages(): array
    {
        return [
            'name.unique' => self::UNIQUE_VALIDATION_MESSAGE,
        ];
    }
}
