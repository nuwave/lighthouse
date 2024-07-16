<?php declare(strict_types=1);

namespace Tests\Unit\Events;

use Illuminate\Contracts\Events\Dispatcher as EventsDispatcher;
use Nuwave\Lighthouse\Events\BuildSchemaString;
use Tests\TestCase;

final class BuildSchemaStringTest extends TestCase
{
    public function testInjectsSourceSchemaIntoEvent(): void
    {
        $dispatcher = $this->app->make(EventsDispatcher::class);
        $dispatcher->listen(BuildSchemaString::class, function (BuildSchemaString $buildSchemaString): void {
            $this->assertSame(self::PLACEHOLDER_QUERY, $buildSchemaString->userSchema);
        });

        $this->buildSchema(self::PLACEHOLDER_QUERY);
    }

    public function testAddAdditionalSchemaThroughEvent(): void
    {
        $dispatcher = $this->app->make(EventsDispatcher::class);
        $dispatcher->listen(BuildSchemaString::class, fn (): string => "
            extend type Query {
                sayHello: String @field(resolver: \"{$this->qualifyTestResolver('resolveSayHello')}\")
            }
        ");

        $this->schema = /** @lang GraphQL */ "
        type Query {
            foo: String @field(resolver: \"{$this->qualifyTestResolver('resolveFoo')}\")
        }
        ";

        $queryForBaseSchema = /** @lang GraphQL */ '
        {
            foo
        }
        ';
        $this->graphQL($queryForBaseSchema)->assertJson([
            'data' => [
                'foo' => 'foo',
            ],
        ]);

        $queryForAdditionalSchema = /** @lang GraphQL */ '
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

    public static function resolveSayHello(): string
    {
        return 'hello';
    }

    public static function resolveFoo(): string
    {
        return 'foo';
    }
}
