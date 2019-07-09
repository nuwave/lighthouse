<?php

namespace Tests\Utils\Directives;

use Nuwave\Lighthouse\Schema\Directives\ValidationDirective;

class FooValidationDirective extends ValidationDirective
{
    /**
     * Name of the directive.
     *
     * @return string
     */
    public function name(): string
    {
        return 'fooValidation';
    }

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
