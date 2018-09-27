<?php

namespace Tests\Integration\Events;

use Tests\TestCase;
use Nuwave\Lighthouse\Events\BuildingAST;

class BuildingASTTest extends TestCase
{
    public function itInjectsSourceSchemaIntoEvent()
    {
        $this->schema = 'foo';
    
        resolve('events')->listen(
            BuildingAST::class,
            function (BuildingAST $buildingAST) {
                $this->assertSame('foo', $buildingAST->userSchema);
            }
        );
    }
    
    /**
     * @test
     */
    public function itCanAddAdditionalSchemaThroughEvent()
    {
        resolve('events')->listen(
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
        query {
            foo
        }
        ';
        $resultForFoo = $this->execute($schema, $queryForBaseSchema);
        $this->assertSame('foo', array_get($resultForFoo, 'data.foo'));
        
        $queryForAdditionalSchema = '
        query {
            sayHello
        }
        ';
        $resultForSayHello = $this->execute($schema, $queryForAdditionalSchema);
        $this->assertSame('hello', array_get($resultForSayHello, 'data.sayHello'));
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
