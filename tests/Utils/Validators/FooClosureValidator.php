<?php

namespace Tests\Utils\Validators;

use Closure;
use Nuwave\Lighthouse\Validation\Validator;

class FooClosureValidator extends Validator
{
    public function rules(): array
    {
        return [
            'foo' => [
                function (string $attribute, $value, Closure $fail) {
                    if ($value !== 'foo') {
                        $fail('The '.$attribute.' field must have a value of "foo".');
                    }
                },
            ],
        ];
    }
}
