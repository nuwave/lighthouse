<?php

namespace Tests\Integration\Events;

use Tests\TestCase;
use Illuminate\Support\Arr;
use Nuwave\Lighthouse\Events\BuildingAST;

class BuildingASTTest extends TestCase
{
    /**
     * @test
     */
    public function itInjectsSourceSchemaIntoEvent()
    {
        $schema = $this->placeholderQuery();

        app('events')->listen(
            BuildingAST::class,
            function (BuildingAST $buildingAST) use ($schema){
                $this->assertSame($schema, $buildingAST->userSchema);
            }
        );

        $this->buildSchema($schema);
    }

    /**
     * @test
     */
    public function itCanAddAdditionalSchemaThroughEvent()
    {
        app('events')->listen(
            BuildingAST::class,
            function (BuildingAST $buildingAST) {
                $resolver = $this->getResolver('resolveSayHello');

                return "
                extend type Query {
                    sayHello: String @field(resolver: \"$resolver\")
                }
                ";
            }
        );

        $resolver = $this->getResolver('resolveFoo');

        $schema = "
        type Query {
            foo: String @field(resolver: \"$resolver\")
        }
        ";

        $queryForBaseSchema = '
        {
            foo
        }
        ';
        $resultForFoo = $this->execute($schema, $queryForBaseSchema);
        $this->assertSame('foo', Arr::get($resultForFoo, 'data.foo'));

        $queryForAdditionalSchema = '
        {
            sayHello
        }
        ';
        $resultForSayHello = $this->execute($schema, $queryForAdditionalSchema);
        $this->assertSame('hello', Arr::get($resultForSayHello, 'data.sayHello'));
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
