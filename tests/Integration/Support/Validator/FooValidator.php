<?php

namespace Tests\Integration\Support\Validator;

use Nuwave\Lighthouse\Support\Validator\Validator;

class FooValidator extends Validator
{
    /**
     * Get rules for field.
     *
     * @return array
     */
    protected function rules()
    {
        return [
            'bar' => ['required', 'min:5'],
            'baz' => ['required', 'min:5'],
        ];
    }
}
