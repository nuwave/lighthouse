<?php

namespace Tests\Unit\Events;

use Nuwave\Lighthouse\Events\BuildSchemaString;
use Tests\TestCase;

class BuildSchemaStringTest extends TestCase
{
    public function testInjectsSourceSchemaIntoEvent(): void
    {
        app('events')->listen(
            BuildSchemaString::class,
            function (BuildSchemaString $buildSchemaString): void {
                $this->assertSame(self::PLACEHOLDER_QUERY, $buildSchemaString->userSchema);
            }
        );

        $this->buildSchema(self::PLACEHOLDER_QUERY);
    }

    public function testCanAddAdditionalSchemaThroughEvent(): void
    {
        app('events')->listen(
            BuildSchemaString::class,
            function (BuildSchemaString $buildSchemaString): string {
                return "
                extend type Query {
                    sayHello: String @field(resolver: \"{$this->qualifyTestResolver('resolveSayHello')}\")
                }
                ";
            }
        );

        $this->schema = "
        type Query {
            foo: String @field(resolver: \"{$this->qualifyTestResolver('resolveFoo')}\")
        }
        ";

        $queryForBaseSchema = '
        {
            foo
        }
        ';
        $this->graphQL($queryForBaseSchema)->assertJson([
            'data' => [
                'foo' => 'foo',
            ],
        ]);

        $queryForAdditionalSchema = '
        {
            sayHello
        }
        ';
        $this->graphQL($queryForAdditionalSchema)->assertJson([
            'data' => [
                'sayHello' => 'hello',
            ],
        ]);
    }

    public function resolveSayHello(): string
    {
        return 'hello';
    }

    public function resolveFoo(): string
    {
        return 'foo';
    }
}
