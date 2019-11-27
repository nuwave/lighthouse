<?php

namespace Tests\Unit\Schema\Directives;

use Nuwave\Lighthouse\Exceptions\DirectiveException;
use Tests\TestCase;

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
