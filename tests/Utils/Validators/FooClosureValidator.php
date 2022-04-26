<?php

namespace Tests\Utils\Validators;

use Closure;
use Nuwave\Lighthouse\Validation\Validator;

final class FooClosureValidator extends Validator
{
    public static function notFoo(string $attribute): string
    {
        return 'The ' . $attribute . ' field must have a value of "foo".';
    }

    public function rules(): array
    {
        return [
            'foo' => [
                static function (string $attribute, $value, Closure $fail): void {
                    if ('foo' !== $value) {
                        $fail(self::notFoo($attribute));
                    }
                },
            ],
        ];
    }
}
