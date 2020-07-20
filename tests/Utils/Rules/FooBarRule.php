<?php

namespace Tests\Utils\Rules;

use Illuminate\Contracts\Validation\Rule;

class FooBarRule implements Rule
{
    public const MESSAGE = 'This rule was triggered.';

    /**
     * Determine if the validation rule passes.
     *
     * @param  string  $attribute
     * @param  mixed  $value The user-given value
     */
    public function passes($attribute, $value): bool
    {
        return false;
    }

    /**
     * Get the validation error message.
     */
    public function message(): string
    {
        return self::MESSAGE;
    }
}
