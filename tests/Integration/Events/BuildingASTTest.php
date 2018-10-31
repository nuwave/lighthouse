<?php

namespace Tests\Integration\Events;

use Tests\TestCase;
use Nuwave\Lighthouse\Events\BuildingAST;

class BuildingASTTest extends TestCase
{
    /**
     * @test
     */
    public function itInjectsSourceSchemaIntoEvent()
    {
        $schema = $this->placeholderQuery();
        
        resolve('events')->listen(
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
        {
            foo
        }
        ';
        $resultForFoo = $this->execute($schema, $queryForBaseSchema);
        $this->assertSame('foo', array_get($resultForFoo, 'data.foo'));
        
        $queryForAdditionalSchema = '
        {
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
