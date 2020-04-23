<?php

namespace Tests\Unit\Execution\Arguments;

use Nuwave\Lighthouse\Execution\Arguments\Argument;
use Nuwave\Lighthouse\Execution\Arguments\ArgumentSet;
use Nuwave\Lighthouse\Execution\Arguments\ArgumentSetFactory;
use Nuwave\Lighthouse\Schema\AST\ASTBuilder;
use Nuwave\Lighthouse\Schema\AST\ASTHelper;
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

    protected function rootQueryArgumentSet(array $args): ArgumentSet
    {
        /** @var \Nuwave\Lighthouse\Schema\AST\ASTBuilder $astBuilder */
        $astBuilder = $this->app->make(ASTBuilder::class);
        $documentAST = $astBuilder->documentAST();
        /** @var \Nuwave\Lighthouse\Execution\Arguments\ArgumentSetFactory $factory */
        $factory = $this->app->make(ArgumentSetFactory::class);

        /** @var \GraphQL\Language\AST\ObjectTypeDefinitionNode $queryType */
        $queryType = $documentAST->types['Query'];

        return $factory->wrapArgs(
            ASTHelper::firstByName($queryType->fields, 'foo'),
            $args
        );
    }
}
