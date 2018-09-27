<?php

namespace Tests\Integration\Events;

use Nuwave\Lighthouse\Events\BuildingAST;
use Tests\TestCase;

class BuildingASTTest extends TestCase
{
    protected $eventDispatched = false;

    /**
     * @test
     */
    public function itCanAddAdditionalSchemaThroughEvent()
    {
        $this->registerListeners();
        $resolver = $this->getResolver('resolveFoo');

        $schema = "
            type Query {
                foo: String @field(resolver: \"$resolver\")
            }
        ";

        $queryForBaseSchema = '
        query {
            foo
        }
        ';

        $queryForAdditionalSchema = '
        query {
            sayHello
        }
        ';

        $resultForFoo = $this->execute($schema, $queryForBaseSchema);
        $resultForSayHello = $this->execute($schema, $queryForAdditionalSchema);

        $this->assertTrue($this->eventDispatched);
        $this->assertEquals('foo', array_get($resultForFoo, 'data.foo'));
        $this->assertEquals('hello', array_get($resultForSayHello, 'data.sayHello'));
    }

    protected function registerListeners()
    {
        $this->app['events']->listen(BuildingAST::class, function () {
            $resolver = $this->getResolver('resolveSayHello');
            $this->eventDispatched = true;

            return "
            extend type Query {
                sayHello: String @field(resolver: \"$resolver\")
            }
            ";
        });
    }

    public function resolveSayHello(): string
    {
        return 'hello';
    }

    public function resolveFoo(): string
    {
        return 'foo';
    }

    protected function getResolver(string $method): string
    {
        return addslashes(self::class) . "@{$method}";
    }
}
