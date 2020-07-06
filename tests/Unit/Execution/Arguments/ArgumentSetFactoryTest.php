<?php

namespace Tests\Unit\Execution\Arguments;

use GraphQL\Type\Definition\Type;
use Nuwave\Lighthouse\Execution\Arguments\Argument;
use Nuwave\Lighthouse\Execution\Arguments\ArgumentSet;
use Nuwave\Lighthouse\Execution\Arguments\ArgumentSetFactory;
use Nuwave\Lighthouse\Execution\Arguments\ListType;
use Nuwave\Lighthouse\Execution\Arguments\NamedType;
use Nuwave\Lighthouse\Schema\AST\ASTBuilder;
use Nuwave\Lighthouse\Schema\AST\ASTHelper;
use Nuwave\Lighthouse\Schema\RootType;
use Tests\TestCase;

class ArgumentSetFactoryTest extends TestCase
{
    public function testSimpleField(): void
    {
        $this->schema = /** @lang GraphQL */ '
        type Query {
            foo(bar: Int): Int
        }
        ';

        $argumentSet = $this->rootQueryArgumentSet([
            'bar' => 123,
        ]);

        $this->assertCount(1, $argumentSet->arguments);

        $bar = $argumentSet->arguments['bar'];
        $this->assertInstanceOf(Argument::class, $bar);
        $this->assertSame(123, $bar->value);
    }

    public function testNullableList(): void
    {
        $this->schema = /** @lang GraphQL */ '
        type Query {
            foo(bar: [Int!]): Int
        }
        ';

        $argumentSet = $this->rootQueryArgumentSet([
            'bar' => null,
        ]);

        $this->assertCount(1, $argumentSet->arguments);

        $bar = $argumentSet->arguments['bar'];
        $this->assertInstanceOf(Argument::class, $bar);
        $this->assertNull($bar->value);
    }

    public function testItsListsAllTheWayDown(): void
    {
        $this->schema = /** @lang GraphQL */ '
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
        ';

        $barValue =
            // Level 1
            [
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

        /** @var \Nuwave\Lighthouse\Execution\Arguments\Argument $bar */
        $bar = $argumentSet->arguments['bar'];
        $this->assertInstanceOf(Argument::class, $bar);
        $this->assertSame($barValue, $bar->value);

        /** @var \Nuwave\Lighthouse\Execution\Arguments\ListType $firstLevel */
        $firstLevel = $bar->type;
        $this->assertInstanceOf(ListType::class, $firstLevel);
        $this->assertFalse($firstLevel->nonNull);

        /** @var \Nuwave\Lighthouse\Execution\Arguments\ListType $secondLevel */
        $secondLevel = $firstLevel->type;
        $this->assertInstanceOf(ListType::class, $secondLevel);
        $this->assertTrue($secondLevel->nonNull);

        /** @var \Nuwave\Lighthouse\Execution\Arguments\ListType $thirdLevel */
        $thirdLevel = $secondLevel->type;
        $this->assertInstanceOf(ListType::class, $thirdLevel);
        $this->assertFalse($thirdLevel->nonNull);

        /** @var \Nuwave\Lighthouse\Execution\Arguments\ListType $fourthLevel */
        $fourthLevel = $thirdLevel->type;
        $this->assertInstanceOf(ListType::class, $fourthLevel);
        $this->assertTrue($fourthLevel->nonNull);

        /** @var \Nuwave\Lighthouse\Execution\Arguments\NamedType $finalLevel */
        $finalLevel = $fourthLevel->type;
        $this->assertInstanceOf(NamedType::class, $finalLevel);
        $this->assertSame(Type::INT, $finalLevel->name);
        $this->assertFalse($finalLevel->nonNull);
    }

    public function testNullableInputObject(): void
    {
        $this->schema = /** @lang GraphQL */ '
        type Query {
            foo(bar: Bar): Int
        }

        input Bar {
            baz: ID
        }
        ';

        $argumentSet = $this->rootQueryArgumentSet([
            'bar' => null,
        ]);

        $this->assertCount(1, $argumentSet->arguments);

        $bar = $argumentSet->arguments['bar'];
        $this->assertInstanceOf(Argument::class, $bar);
        $this->assertNull($bar->value);
    }

    public function testWithUndefined(): void
    {
        $this->schema = /** @lang GraphQL */ '
        type Query {
            foo(bar: ID): Int
        }
        ';

        $argumentSet = $this->rootQueryArgumentSet([]);

        $this->assertCount(0, $argumentSet->arguments);

        $this->assertCount(1, $argumentSet->argumentsWithUndefined());

        $bar = $argumentSet->argumentsWithUndefined()['bar'];
        $this->assertInstanceOf(Argument::class, $bar);
        $this->assertNull($bar->value);
    }

    /**
     * @param  array<string, mixed>  $args
     */
    protected function rootQueryArgumentSet(array $args): ArgumentSet
    {
        /** @var \Nuwave\Lighthouse\Schema\AST\ASTBuilder $astBuilder */
        $astBuilder = $this->app->make(ASTBuilder::class);
        $documentAST = $astBuilder->documentAST();

        /** @var \GraphQL\Language\AST\ObjectTypeDefinitionNode $queryType */
        $queryType = $documentAST->types[RootType::QUERY];

        /** @var array<\GraphQL\Language\AST\FieldDefinitionNode> $fields */
        $fields = $queryType->fields;

        /** @var \GraphQL\Language\AST\FieldDefinitionNode $fooField */
        $fooField = ASTHelper::firstByName($fields, 'foo');

        /** @var \Nuwave\Lighthouse\Execution\Arguments\ArgumentSetFactory $factory */
        $factory = $this->app->make(ArgumentSetFactory::class);

        return $factory->wrapArgs($fooField, $args);
    }
}
