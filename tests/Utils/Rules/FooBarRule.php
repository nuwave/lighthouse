<?php declare(strict_types=1);

namespace Tests\Utils\Rules;

use Illuminate\Contracts\Validation\Rule;

final class FooBarRule implements Rule
{
    public const MESSAGE = 'This rule was triggered.';

    /**
     * Determine if the validation rule passes.
     *
     * @param  string  $attribute
     * @param  mixed  $value  the user-given value
     */
    public function passes($attribute, $value): bool
    {
        return false;
    }

    /** Get the validation error message. */
    public function message(): string
    {
        return self::MESSAGE;
    }
}
