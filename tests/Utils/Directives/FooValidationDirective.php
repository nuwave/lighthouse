<?php

namespace Tests\Utils\Directives;

use Nuwave\Lighthouse\Schema\Directives\ValidationDirective;

class FooValidationDirective extends ValidationDirective
{
    public static function definition(): string
    {
        return /** @lang GraphQL */ <<<'SDL'
directive @fooValidation on ARGUMENT_DEFINITION | INPUT_FIELD_DEFINITION
SDL;
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
