<?php

namespace Tests\Utils\Validators;

use Nuwave\Lighthouse\Execution\GraphQLValidator;

class FooValidator extends GraphQLValidator
{
    public function getRules()
    {
        return [
            'bar' => 'email',
        ];
    }

    public function messages()
    {
        return [
            'foo.bar' => 'foobar',
        ];
    }
}
