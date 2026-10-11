<?php declare(strict_types=1);

namespace Tests\Unit\Execution\Arguments;

use GraphQL\Language\AST\FieldDefinitionNode;
use GraphQL\Language\AST\ObjectTypeDefinitionNode;
use GraphQL\Type\Definition\Type;
use Nuwave\Lighthouse\Execution\Arguments\ArgumentSet;
use Nuwave\Lighthouse\Execution\Arguments\ArgumentSetFactory;
use Nuwave\Lighthouse\Execution\Arguments\ListType;
use Nuwave\Lighthouse\Execution\Arguments\NamedType;
use Nuwave\Lighthouse\Schema\AST\ASTBuilder;
use Nuwave\Lighthouse\Schema\AST\ASTHelper;
use Nuwave\Lighthouse\Schema\RootType;
use Tests\TestCase;

final class ArgumentSetFactoryTest extends TestCase
{
    public function testSimpleField(): void
    {
        $this->schema = /** @lang GraphQL */ <<<'GRAPHQL'
        type Query {
            foo(bar: Int): Int
        }
        GRAPHQL;

        $argumentSet = $this->rootQueryArgumentSet([
            'bar' => 123,
        ]);

        $this->assertCount(1, $argumentSet->arguments);

        $bar = $argumentSet->arguments['bar'];
        $this->assertSame(123, $bar->value);
    }

    public function testNullableList(): void
    {
        $this->schema = /** @lang GraphQL */ <<<'GRAPHQL'
        type Query {
            foo(bar: [Int!]): Int
        }
        GRAPHQL;

        $argumentSet = $this->rootQueryArgumentSet([
            'bar' => null,
        ]);

        $this->assertCount(1, $argumentSet->arguments);

        $bar = $argumentSet->arguments['bar'];
        $this->assertNull($bar->value);
    }

    public function testItsListsAllTheWayDown(): void
    {
        $this->schema = /** @lang GraphQL */ <<<'GRAPHQL'
        type Query {
            foo(bar:
                # Level 1
                [
                    # Level 2
                    [
                        # Level 3
                        [
                            # Level 4
                            [
                                Int
                            ]!
                        ]
                    ]!
                ]
            ): Int
        }
        GRAPHQL;

        // Level 1
        $barValue = [
            // Level 2
            [
                // Level 3
                [
                    // Level 4
                    [
                        1, 2,
                    ],
                    [
                        3, null,
                    ],
                ],
                null,
            ],
        ];

        $argumentSet = $this->rootQueryArgumentSet([
            'bar' => $barValue,
        ]);

        $this->assertCount(1, $argumentSet->arguments);

        $bar = $argumentSet->arguments['bar'];
        $this->assertSame($barValue, $bar->value);

        $firstLevel = $bar->type;
        $this->assertInstanceOf(ListType::class, $firstLevel);
        $this->assertFalse($firstLevel->nonNull);

        $secondLevel = $firstLevel->type;
        $this->assertInstanceOf(ListType::class, $secondLevel);
        $this->assertTrue($secondLevel->nonNull);

        $thirdLevel = $secondLevel->type;
        $this->assertInstanceOf(ListType::class, $thirdLevel);
        $this->assertFalse($thirdLevel->nonNull);

        $fourthLevel = $thirdLevel->type;
        $this->assertInstanceOf(ListType::class, $fourthLevel);
        $this->assertTrue($fourthLevel->nonNull);

        $finalLevel = $fourthLevel->type;
        $this->assertInstanceOf(NamedType::class, $finalLevel);
        $this->assertSame(Type::INT, $finalLevel->name);
        $this->assertFalse($finalLevel->nonNull);
    }

    public function testNullableInputObject(): void
    {
        $this->schema = /** @lang GraphQL */ <<<'GRAPHQL'
        type Query {
            foo(bar: Bar): Int
        }

        input Bar {
            baz: ID
        }
        GRAPHQL;

        $argumentSet = $this->rootQueryArgumentSet([
            'bar' => null,
        ]);

        $this->assertCount(1, $argumentSet->arguments);

        $bar = $argumentSet->arguments['bar'];
        $this->assertNull($bar->value);
    }

    public function testWithUndefined(): void
    {
        $this->schema = /** @lang GraphQL */ <<<'GRAPHQL'
        type Query {
            foo(bar: ID): Int
        }
        GRAPHQL;

        $argumentSet = $this->rootQueryArgumentSet([]);

        $this->assertCount(0, $argumentSet->arguments);

        $this->assertCount(1, $argumentSet->argumentsWithUndefined());

        $bar = $argumentSet->argumentsWithUndefined()['bar'];
        $this->assertNull($bar->value);
    }

    /** @param  array<string, mixed>  $args */
    private function rootQueryArgumentSet(array $args): ArgumentSet
    {
        $astBuilder = $this->app->make(ASTBuilder::class);
        $documentAST = $astBuilder->documentAST();

        $queryType = $documentAST->types[RootType::QUERY];
        $this->assertInstanceOf(ObjectTypeDefinitionNode::class, $queryType);

        $fields = $queryType->fields;

        $fooField = ASTHelper::firstByName($fields, 'foo');
        $this->assertInstanceOf(FieldDefinitionNode::class, $fooField);

        $factory = $this->app->make(ArgumentSetFactory::class);

        return $factory->wrapArgs($fooField, $args);
    }
}
