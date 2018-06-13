<?php

namespace Tests\Unit\Schema\Directives\Args;

use Tests\TestCase;

class ValidateDirectiveTest extends TestCase
{
    public function testValidateScalarField()
    {
        $this->execute('
            type Query {
                foo(bar: String @validate(rules: ["alpha"])): String
            }
        ', '
            {
                foo(bar: "?!")
            }
        ');
    }
}
