<?php

namespace Tests\Unit\Schema\Directives;

use Tests\TestCase;
use Nuwave\Lighthouse\Exceptions\DirectiveException;

class CountDirectiveTest extends TestCase
{
    public function testRequireRelationOrModelArgument()
    {
        $this->schema = '
        type Query {
            users: Int! @count
        }
        ';

        $this->expectException(DirectiveException::class);
        $this->graphQL('
        {
            users
        }
        ');
    }
}
