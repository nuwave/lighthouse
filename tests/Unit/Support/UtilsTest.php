<?php

namespace Tests\Unit\Support\Http\Middleware;

use Tests\TestCase;
use Nuwave\Lighthouse\Support\Utils;

final class UtilsTest extends TestCase
{
    public function testColumnNameToGraphQLName(): void
    {
        $this->assertSame(
            'COLUMN_NAME',
            Utils::columnNameToGraphQLName('column_name')
        );

        $this->assertSame(
            'COLUMN_NAME',
            Utils::columnNameToGraphQLName('columnName')
        );

        $this->assertSame(
            'SOME_NESTED_COLUMN_NAME',
            Utils::columnNameToGraphQLName('some.nested.column_name')
        );

        $this->assertSame(
            'COLUMN_NAME',
            Utils::columnNameToGraphQLName('$columnName')
        );

        $this->assertSame(
            '_123_COLUMN_NAME',
            Utils::columnNameToGraphQLName('123_column_name')
        );

        $this->assertSame(
            '_123_COLUMN_NAME',
            Utils::columnNameToGraphQLName('123Column$Name')
        );
    }
}
