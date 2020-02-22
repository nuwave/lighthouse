<?php

namespace Tests\Utils\Directives;

use Nuwave\Lighthouse\Validation\ValidateDirective;

class FooValidateDirective extends ValidateDirective
{
    /**
     * @return mixed[]
     */
    public function rules(): array
    {
        return [
            'foo' => ['alpha'],
        ];
    }

    /**
     * @return string[]
     */
    public function messages(): array
    {
        return [];
    }
}
