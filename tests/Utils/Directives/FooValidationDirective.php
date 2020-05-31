<?php

namespace Tests\Utils\Directives;

use Nuwave\Lighthouse\Schema\Directives\ValidationDirective;

class FooValidationDirective extends ValidationDirective
{
    public function rules(): array
    {
        return [
            'foo' => ['alpha'],
        ];
    }

    public function messages(): array
    {
        return [];
    }
}
