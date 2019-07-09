<?php

namespace Tests\Utils\Directives;

use Illuminate\Validation\Rule;
use Nuwave\Lighthouse\Schema\Directives\ValidationDirective;

class ComplexValidationDirective extends ValidationDirective
{
    const UNIQUE_VALIDATION_MESSAGE = 'Used to test this exact validation is triggered';

    /**
     * Name of the directive.
     *
     * @return string
     */
    public function name(): string
    {
        return 'complexValidation';
    }

    /**
     * @return mixed[]
     */
    public function rules(): array
    {
        return [
            'input.id' => ['required'],
            'input.name' => ['sometimes', Rule::unique('users', 'name')->ignore($this->args['id'], 'id')],
        ];
    }

    /**
     * @return string[]
     */
    public function messages(): array
    {
        return [
            'input.name.unique' => self::UNIQUE_VALIDATION_MESSAGE,
        ];
    }
}
