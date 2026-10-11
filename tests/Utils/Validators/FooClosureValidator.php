<?php declare(strict_types=1);

namespace Tests\Utils\Validators;

use Nuwave\Lighthouse\Validation\Validator;

final class FooClosureValidator extends Validator
{
    public static function notFoo(string $attribute): string
    {
        return "The {$attribute} field must have a value of \"foo\".";
    }

    /** @return array{foo: array<\Closure(string $attribute, mixed $value, \Closure $fail): void>} */
    public function rules(): array
    {
        return [
            'foo' => [
                static function (string $attribute, $value, \Closure $fail): void {
                    if ($value !== 'foo') {
                        $fail(self::notFoo($attribute));
                    }
                },
            ],
        ];
    }
}
