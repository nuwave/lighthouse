<?php

namespace Tests\Utils\Rules;

use Illuminate\Contracts\Validation\Rule;

class FooBarRule implements Rule
{
    /**
     * Create a new rule instance.
     *
     * @return void
     */
    public function __construct()
    {
        //
    }

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
        $valid = true;

        $value = is_array($value) ? $value : [$value];

        foreach ($value as $item) {
            if ($item !== 'baz') {
                $valid = false;
                break;
            }
        }

        return $valid;
    }

    /**
     * Get the validation error message.
     *
     * @return string
     */
    public function message()
    {
        return 'Some FooBarRule error message.';
    }
}
