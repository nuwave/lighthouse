<?php

namespace Tests\Integration\Events;

use Tests\TestCase;
use Nuwave\Lighthouse\Events\BuildSchemaString;

class BuildSchemaStringTest extends TestCase
{
    /**
     * @test
     */
    public function itInjectsSourceSchemaIntoEvent(): void
    {
        $schema = $this->placeholderQuery();

        app('events')->listen(
            BuildSchemaString::class,
            function (BuildSchemaString $buildSchemaString) use ($schema): void {
                $this->assertSame($schema, $buildSchemaString->userSchema);
            }
        );

        $this->buildSchema($schema);
    }

    /**
     * @test
     */
    public function itCanAddAdditionalSchemaThroughEvent(): void
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
