<?php

namespace Tests\Unit\Events;

use Illuminate\Contracts\Events\Dispatcher;
use Nuwave\Lighthouse\Events\BuildSchemaString;
use Tests\TestCase;

class BuildSchemaStringTest extends TestCase
{
    public function testInjectsSourceSchemaIntoEvent(): void
    {
        /** @var \Illuminate\Contracts\Events\Dispatcher $dispatcher */
        $dispatcher = app(Dispatcher::class);
        $dispatcher->listen(
            BuildSchemaString::class,
            function (BuildSchemaString $buildSchemaString): void {
                $this->assertSame(self::PLACEHOLDER_QUERY, $buildSchemaString->userSchema);
            }
        );

        $this->buildSchema(self::PLACEHOLDER_QUERY);
    }

    public function testAddAdditionalSchemaThroughEvent(): void
    {
        /** @var \Illuminate\Contracts\Events\Dispatcher $dispatcher */
        $dispatcher = app(Dispatcher::class);
        $dispatcher->listen(
            BuildSchemaString::class,
            function (): string {
                return /** @lang GraphQL */ "
                extend type Query {
                    sayHello: String @field(resolver: \"{$this->qualifyTestResolver('resolveSayHello')}\")
                }
                ";
            }
        );

        $this->schema = /** @lang GraphQL */"
        type Query {
            foo: String @field(resolver: \"{$this->qualifyTestResolver('resolveFoo')}\")
        }
        ";

        $queryForBaseSchema = /** @lang GraphQL */'
        {
            foo
        }
        ';
        $this->graphQL($queryForBaseSchema)->assertJson([
            'data' => [
                'foo' => 'foo',
            ],
        ]);

        $queryForAdditionalSchema = /** @lang GraphQL */'
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
