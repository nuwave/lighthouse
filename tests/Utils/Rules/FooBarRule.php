<?php

namespace Tests\Utils\Rules;

use Illuminate\Contracts\Validation\Rule;

class FooBarRule implements Rule
{
    const MESSAGE = 'This rule was triggered.';

    /**
     * Determine if the validation rule passes.
     *
     * @param  string  $attribute
     * @param  mixed  $value
     *
     * @return bool
     */
    public function passes($attribute, $value)
    {
        return false;
    }

    /**
     * Get the validation error message.
     *
     * @return string
     */
    public function message()
    {
        return self::MESSAGE;
    }
}
