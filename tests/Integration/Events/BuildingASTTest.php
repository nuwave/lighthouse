<?php

namespace Tests\Integration\Events;

use Tests\TestCase;
use Nuwave\Lighthouse\Events\BuildingAST;

class BuildingASTTest extends TestCase
{
    /**
     * @test
     */
    public function itInjectsSourceSchemaIntoEvent(): void
    {
        $schema = $this->placeholderQuery();

        app('events')->listen(
            BuildingAST::class,
            function (BuildingAST $buildingAST) use ($schema): void {
                $this->assertSame($schema, $buildingAST->userSchema);
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
            BuildingAST::class,
            function (BuildingAST $buildingAST): string {
                $resolver = $this->getResolver('resolveSayHello');

                return "
                extend type Query {
                    sayHello: String @field(resolver: \"{$resolver}\")
                }
                ";
            }
        );

        $resolver = $this->getResolver('resolveFoo');

        $this->schema = "
        type Query {
            foo: String @field(resolver: \"{$resolver}\")
        }
        ";

        $queryForBaseSchema = '
        {
            foo
        }
        ';
        $this->query($queryForBaseSchema)->assertJson([
            'data' => [
                'foo' => 'foo'
            ]
        ]);

        $queryForAdditionalSchema = '
        {
            sayHello
        }
        ';
        $this->query($queryForAdditionalSchema)->assertJson([
            'data' => [
                'sayHello' => 'hello'
            ]
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

    protected function getResolver(string $method): string
    {
        return addslashes(self::class)."@{$method}";
    }
}
